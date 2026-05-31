<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Product;
use App\Support\SizeStockNormalizer;
use App\Support\VariantColorResolver;
/**
 * Etapa 4: ficha de producto → color → talla → confirmación de pedido.
 */
class ProductPresentationService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected BusinessConfigService $business,
        protected ProductMediaService $media,
        protected SalesNudgeService $salesNudge
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

        $this->tools->executeSendProductImage(
            $state,
            $product->id,
            null,
            "✨ {$product->name} 📸"
        );

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
        $salesStage = $ctx['sales_stage'] ?? null;

        if (! in_array($salesStage, ['awaiting_color_selection', 'awaiting_size_selection'], true)) {
            return null;
        }

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $color = $this->extractColorFromMessage($message, $productId);

        if ($salesStage === 'awaiting_size_selection' && ! $this->isExplicitColorPick($message, $productId, $color)) {
            return null;
        }

        if ($productId <= 0 || $color === '') {
            return [
                'text' => $this->business->applyBrandCta('Dime el color que deseas (ej: borgoña, negro).'),
                'metadata' => [],
            ];
        }

        $imageResult = $this->tools->executeSendProductImage(
            $state,
            $productId,
            $color,
            "Color {$color} 📸"
        );
        $stock = $this->tools->executeCheckStock($state, $productId, $color);
        $canonicalColor = (string) ($stock['color'] ?? $color);
        $stockBySize = SizeStockNormalizer::normalize($stock['stock_by_size'] ?? []);
        $sizes = $this->formatAvailableSizes($stockBySize);

        $ctx['current_color'] = $canonicalColor;
        $ctx['sales_stage'] = 'awaiting_size_selection';
        $state->context = $ctx;
        $state->save();

        $product = Product::find($productId);
        $validation = $product ? PriceValidatorService::validateProductPrice($product) : ['final_price' => 0];
        $price = number_format((float) ($validation['final_price'] ?? 0), 0);

        // Si hay foto del color, enviar imagen + texto tallas
        // Si NO hay foto, enviar SOLO texto sin foto de otro color
        if ($imageResult['success'] ?? false) {
            $text = "Color {$color} 📸\n💰 S/{$price}\n📏 Tallas con stock: {$sizes}\n\n¿Qué talla necesitas?";
        } else {
            $text = "Color {$color} seleccionado 💕 (foto en camino / consulta con asesor)\n💰 S/{$price}\n📏 Tallas con stock: {$sizes}\n\n¿Qué talla necesitas?";
        }

        // Generar botones dinámicos basados en stock real
        $availableSizes = array_filter($stockBySize, fn ($qty) => (int) $qty > 0);
        $sizeKeys = array_keys($availableSizes);

        if (count($sizeKeys) === 0) {
            return [
                'text' => $this->business->applyBrandCta("Lo siento, el color {$color} no tiene stock disponible en ninguna talla. ¿Quieres otro color?"),
                'metadata' => [],
            ];
        }

        // Guardar tallas disponibles en contexto para validación posterior
        $ctx['available_sizes'] = $sizeKeys;
        $state->context = $ctx;
        $state->save();

        if (count($sizeKeys) <= 3) {
            $buttons = [];
            foreach ($sizeKeys as $size) {
                $buttons[] = [
                    'id' => 'size_' . mb_strtolower($size),
                    'title' => 'Talla ' . strtoupper($size),
                ];
            }
            $this->tools->executeSendInteractiveButtons($state, $text, $buttons, 'Elige talla');
        } else {
            $rows = [];
            foreach (array_slice($sizeKeys, 0, 10) as $size) {
                $rows[] = [
                    'id' => 'size_' . mb_strtolower($size),
                    'title' => 'Talla ' . strtoupper($size),
                    'description' => 'Disponible',
                ];
            }
            $this->tools->executeSendInteractiveList(
                $state,
                $text,
                'Ver tallas',
                [['title' => 'Tallas', 'rows' => $rows]],
                'Elige talla'
            );
        }

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

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $color = (string) ($ctx['current_color'] ?? '');
        $stock = $this->tools->executeCheckStock($state, $productId, $color);
        $stockBySize = SizeStockNormalizer::normalize($stock['stock_by_size'] ?? []);
        $contextSizes = array_map(fn ($s) => strtoupper(trim((string) $s)), $ctx['available_sizes'] ?? []);

        $size = SizeStockNormalizer::resolveFromMessage($message, $stockBySize, $contextSizes);

        if (! $size) {
            $sizeList = ! empty($contextSizes)
                ? implode(', ', $contextSizes)
                : implode(', ', array_keys(array_filter($stockBySize, fn ($qty) => (int) $qty > 0)));

            return [
                'text' => $this->business->applyBrandCta("Indícame tu talla: {$sizeList}."),
                'metadata' => [],
            ];
        }

        $hasStock = (int) ($stockBySize[$size] ?? 0) > 0;

        if (! $hasStock) {
            $sizeKeys = array_keys(array_filter($stockBySize, fn ($qty) => (int) $qty > 0));
            $sizeList = !empty($sizeKeys) ? implode(', ', array_map('strtoupper', $sizeKeys)) : 'ninguna talla disponible';

            $errorText = "Lo siento, la talla {$size} no está disponible o no tiene stock. Tallas disponibles: {$sizeList}. Por favor, elige otra talla.";

            // Re-mostrar botones con tallas disponibles
            if (count($sizeKeys) <= 3) {
                $buttons = [];
                foreach ($sizeKeys as $sz) {
                    $buttons[] = [
                        'id' => 'size_' . mb_strtolower($sz),
                        'title' => 'Talla ' . strtoupper($sz),
                    ];
                }
                $this->tools->executeSendInteractiveButtons($state, $errorText, $buttons, 'Elige talla');
            } else {
                $rows = [];
                foreach (array_slice($sizeKeys, 0, 10) as $sz) {
                    $rows[] = [
                        'id' => 'size_' . mb_strtolower($sz),
                        'title' => 'Talla ' . strtoupper($sz),
                        'description' => 'Disponible',
                    ];
                }
                $this->tools->executeSendInteractiveList(
                    $state,
                    $errorText,
                    'Ver tallas',
                    [['title' => 'Tallas', 'rows' => $rows]],
                    'Elige talla'
                );
            }

            return ['text' => $this->business->applyBrandCta($errorText), 'metadata' => []];
        }

        $ctx['current_size'] = $size;
        $ctx['sales_stage'] = 'awaiting_order_confirmation';
        $state->context = $ctx;
        $state->save();

        $text = $this->salesNudge->orderConfirmationText($state);

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
        $resolved = VariantColorResolver::resolve($productId, $message);
        if ($resolved !== null && $resolved !== '') {
            return $resolved;
        }

        if (preg_match('/\b(borgoña|borgona|rojo|azul|verde|negro|blanco|rosa|lila|morado|beige|nude|celeste|fucsia|camel|naranja)\b/iu', $message, $m)) {
            $requested = mb_strtolower($m[1]);
            $map = VariantColorResolver::variantColorMap($productId);

            return $map[$requested] ?? $requested;
        }

        return mb_strtolower(trim($message));
    }

    public function resolveVariantColorFromMessage(int $productId, string $message): ?string
    {
        return VariantColorResolver::resolve($productId, $message);
    }

    protected function isExplicitColorPick(string $message, int $productId, string $color): bool
    {
        if (preg_match('/pick_color_' . $productId . '_/i', $message)) {
            return true;
        }

        if (preg_match('/\b(otro\s+color|quiero\s+ver|ver\s+en)\b/ui', $message)) {
            return true;
        }

        if (preg_match('/\b(color|foto|imagen|muestrame|mu[eé]strame|ver)\b/ui', $message)) {
            return true;
        }

        if ($color === '') {
            return false;
        }

        $product = Product::with('variants')->find($productId);
        if (! $product) {
            return false;
        }

        foreach ($product->variants as $variant) {
            $variantColor = mb_strtolower(trim((string) $variant->color));
            if ($variantColor === $color || str_contains($variantColor, $color) || str_contains($color, $variantColor)) {
                return true;
            }
        }

        return false;
    }
}
