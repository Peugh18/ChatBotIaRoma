<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Product;
/**
 * Etapa 4: ficha de producto → color → talla → confirmación de pedido.
 */
class ProductPresentationService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected BusinessConfigService $business,
        protected ProductMediaService $media
    ) {
    }

    public function presentProductPick(ConversationState $state, int $productId): array
    {
        $product = Product::with('variants')->find($productId);
        if (!$product) {
            return [
                'text' => $this->business->applyBrandCta('No ubiqué ese vestido. ¿Me pasas el nombre exacto o una foto?'),
                'metadata' => [],
            ];
        }

        $ctx = $state->context ?? [];
        $ctx['current_product_id'] = $product->id;
        $ctx['current_product_name'] = $product->name;
        $ctx['sales_stage'] = 'awaiting_color_selection';
        $state->context = $ctx;
        $state->save();

        $this->tools->executeSendProductImage($state, $product->id, null);

        $text = $this->buildProductCard($product);
        $text .= "\n\n¿En qué color lo deseas? 💕";

        $colors = $this->media->colorsForProduct($product);
        if (empty($colors)) {
            return [
                'text' => $this->business->applyBrandCta("Encontré {$product->name}, pero aún no tiene colores cargados."),
                'metadata' => [],
            ];
        }

        $preferred = (string) ($ctx['image_color_preference'] ?? '');
        if ($preferred !== '') {
            foreach ($colors as $c) {
                if (str_contains(mb_strtolower($c['color']), $preferred)) {
                    return $this->handleColorSelection(
                        $state,
                        'pick_color_' . $product->id . '_' . rawurlencode(mb_strtolower($c['color']))
                    );
                }
            }
        }

        if (count($colors) <= 3) {
            $buttons = [];
            foreach (array_slice($colors, 0, 3) as $c) {
                $buttons[] = [
                    'id' => 'pick_color_' . $product->id . '_' . rawurlencode(mb_strtolower($c['color'])),
                    'title' => mb_substr($c['color'], 0, 20),
                ];
            }
            $this->tools->executeSendInteractiveButtons($state, $text, $buttons, 'Elige color');
        } else {
            $rows = [];
            foreach (array_slice($colors, 0, 10) as $c) {
                $rows[] = [
                    'id' => 'pick_color_' . $product->id . '_' . rawurlencode(mb_strtolower($c['color'])),
                    'title' => mb_substr($c['color'], 0, 24),
                    'description' => ($c['has_stock'] ?? false) ? 'Con stock' : 'Consultar',
                ];
            }
            $this->tools->executeSendInteractiveList(
                $state,
                $text,
                'Ver colores',
                [['title' => 'Colores', 'rows' => $rows]],
                'Elige color'
            );
        }

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    public function buildProductCard(Product $product): string
    {
        $validation = PriceValidatorService::validateProductPrice($product);
        $price = number_format((float) ($validation['final_price'] ?? 0), 0);

        $colors = [];
        $sizes = [];
        $hasStock = false;

        foreach ($product->variants as $variant) {
            $colors[] = $variant->color;
            foreach (($variant->sizes_stock ?? []) as $size => $qty) {
                if ((int) $qty > 0) {
                    $sizes[$size] = true;
                    $hasStock = true;
                }
            }
        }

        $colors = array_values(array_unique(array_filter($colors)));
        $sizeList = array_keys($sizes);
        sort($sizeList);

        $colorLine = empty($colors) ? 'Consultar' : implode(', ', $colors);
        $sizeLine = empty($sizeList) ? 'Consultar' : implode(', ', $sizeList);
        $stockLine = $hasStock ? 'Disponible ✅' : 'Por confirmar';

        return "✨ {$product->name}\n"
            . "💰 S/{$price}\n"
            . "🎨 Colores:\n{$colorLine}\n"
            . "📏 Tallas:\n{$sizeLine}\n"
            . "📦 Stock: {$stockLine}";
    }

    public function handleColorSelection(ConversationState $state, string $message): ?array
    {
        $ctx = $state->context ?? [];
        if (($ctx['sales_stage'] ?? null) !== 'awaiting_color_selection') {
            return null;
        }

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $color = $this->extractColorFromMessage($message, $productId);
        if ($productId <= 0 || $color === '') {
            return [
                'text' => $this->business->applyBrandCta('Dime el color que deseas (ej: borgoña, negro).'),
                'metadata' => [],
            ];
        }

        $imageResult = $this->tools->executeSendProductImage($state, $productId, $color);
        $stock = $this->tools->executeCheckStock($state, $productId, $color);
        $sizes = $this->formatAvailableSizes($stock['stock_by_size'] ?? []);

        $ctx['current_color'] = $color;
        $ctx['sales_stage'] = 'awaiting_size_selection';
        $state->context = $ctx;
        $state->save();

        $product = Product::find($productId);
        $validation = $product ? PriceValidatorService::validateProductPrice($product) : ['final_price' => 0];
        $price = number_format((float) ($validation['final_price'] ?? 0), 0);

        $text = "Color {$color} 📸\n💰 S/{$price}\n📏 Tallas con stock: {$sizes}\n\n¿Qué talla necesitas?";
        $this->tools->executeSendInteractiveButtons(
            $state,
            $text,
            [
                ['id' => 'size_s', 'title' => 'Talla S'],
                ['id' => 'size_m', 'title' => 'Talla M'],
                ['id' => 'size_l', 'title' => 'Talla L'],
            ],
            'Elige talla'
        );

        if (!($imageResult['success'] ?? false)) {
            $text .= "\n(No tengo foto de ese color cargada, pero sí lo tenemos).";
        }

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    public function handleSizeSelection(ConversationState $state, string $message): ?array
    {
        $ctx = $state->context ?? [];
        if (($ctx['sales_stage'] ?? null) !== 'awaiting_size_selection') {
            return null;
        }

        $size = null;
        if (preg_match('/\btalla\s*(s|m|l|xl|xs)\b/ui', $message, $m)) {
            $size = strtoupper($m[1]);
        } elseif (preg_match('/\b(s|m|l|xl|xs)\b/ui', trim($message), $m)) {
            $size = strtoupper($m[1]);
        }

        if (!$size) {
            return [
                'text' => $this->business->applyBrandCta('Indícame tu talla: S, M o L.'),
                'metadata' => [],
            ];
        }

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $color = (string) ($ctx['current_color'] ?? '');
        $stock = $this->tools->executeCheckStock($state, $productId, $color);
        $available = is_array($stock['stock_by_size'] ?? null) && (int) ($stock['stock_by_size'][$size] ?? 0) > 0;

        $ctx['current_size'] = $size;
        $ctx['sales_stage'] = 'awaiting_order_confirmation';
        $state->context = $ctx;
        $state->save();

        $product = Product::find($productId);
        $card = $product ? $this->buildProductCard($product) : '';
        $stockLine = $available ? "Talla {$size} disponible ✅" : "Talla {$size} por confirmar con almacén.";
        $text = "{$card}\n\n{$stockLine}\n\n" . $this->business->orderConfirmationPrompt();

        $this->tools->executeSendInteractiveButtons(
            $state,
            $text,
            [
                ['id' => 'confirm_order', 'title' => 'Sí, quiero'],
                ['id' => 'escalate_human', 'title' => 'Hablar asesor'],
            ],
            'Confirmar pedido'
        );

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    /**
     * @param array<string, int|string> $stockBySize
     */
    protected function formatAvailableSizes(array $stockBySize): string
    {
        $sizes = [];
        foreach ($stockBySize as $size => $qty) {
            if ((int) $qty > 0) {
                $sizes[] = strtoupper((string) $size);
            }
        }

        return empty($sizes) ? 'consultar' : implode(', ', $sizes);
    }

    protected function extractColorFromMessage(string $message, int $productId): string
    {
        if (preg_match('/pick_color_' . $productId . '_([^\\s]+)/i', $message, $m)) {
            return urldecode($m[1]);
        }
        if (preg_match('/\bcolor\s+([a-záéíóúñ]+)/iu', $message, $m)) {
            return mb_strtolower($m[1]);
        }
        if (preg_match('/\b(borgoña|borgona|rojo|azul|verde|negro|blanco|rosa|lila|morado|beige|nude|celeste|fucsia)\b/iu', $message, $m)) {
            return mb_strtolower($m[1]);
        }

        return trim($message);
    }
}
