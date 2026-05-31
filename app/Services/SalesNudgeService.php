<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Product;

/**
 * Respuestas de venta ante dudas, objeciones e intención de compra (sin LLM).
 */
class SalesNudgeService
{
    public function __construct(
        protected BusinessConfigService $business,
        protected ToolExecutorService $tools,
        protected SalesFlowService $salesFlow
    ) {
    }

    /**
     * @return array{text: string, metadata: array}|null
     */
    public function tryRespond(ConversationState $state, string $message, bool $hasImage): ?array
    {
        if ($hasImage) {
            return null;
        }

        $msg = mb_strtolower(trim($message));
        $stage = (string) ($state->context['sales_stage'] ?? '');

        if ($this->isBuyIntent($msg) && in_array($stage, [
            'awaiting_color_selection',
            'awaiting_size_selection',
            'awaiting_product_selection',
        ], true)) {
            return $this->pushTowardCheckout($state, $stage);
        }

        if ($stage === 'awaiting_order_confirmation') {
            return $this->handleConfirmationStageNudge($state, $msg);
        }

        if (in_array($stage, ['awaiting_color_selection', 'awaiting_size_selection'], true)) {
            return $this->handleObjection($state, $msg, $stage);
        }

        return null;
    }

    protected function isBuyIntent(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(lo quiero|la quiero|me lo llevo|separar|separalo|s[eé]paralo|comprar|quiero ese|ese quiero|confirmo|hagamos el pedido|hazme el pedido)\b/u',
            $msg
        );
    }

    /**
     * @return array{text: string, metadata: array}
     */
    protected function pushTowardCheckout(ConversationState $state, string $stage): array
    {
        if ($stage === 'awaiting_size_selection') {
            $sizes = $state->context['available_sizes'] ?? [];
            $sizeList = ! empty($sizes)
                ? implode(', ', array_map('strtoupper', $sizes))
                : 'tu talla';

            return [
                'text' => $this->business->applyBrandCta(
                    config('sales_copy.buy_intent_soft')."\n\nIndícame tu talla: {$sizeList}."
                ),
                'metadata' => [],
            ];
        }

        if ($stage === 'awaiting_color_selection') {
            return [
                'text' => $this->business->applyBrandCta(
                    config('sales_copy.buy_intent_soft')."\n\n¿En qué color lo deseas? 🎨"
                ),
                'metadata' => [],
            ];
        }

        return [
            'text' => $this->business->applyBrandCta(
                config('sales_copy.buy_intent_soft')."\n\nElige el modelo de la lista 💕"
            ),
            'metadata' => [],
        ];
    }

    /**
     * @return array{text: string, metadata: array}|null
     */
    protected function handleConfirmationStageNudge(ConversationState $state, string $msg): ?array
    {
        if ($this->salesFlow->isPositivePublic($msg)) {
            return null;
        }

        if ($this->salesFlow->isNegativePublic($msg)) {
            return null;
        }

        if ($this->matchesObjection($msg, 'price')) {
            return $this->withConfirmButtons(
                $state,
                config('sales_copy.objection_price')
            );
        }

        if ($this->matchesObjection($msg, 'think')) {
            return $this->withConfirmButtons(
                $state,
                config('sales_copy.objection_think')
            );
        }

        if ($this->matchesObjection($msg, 'trust')) {
            return $this->withConfirmButtons(
                $state,
                config('sales_copy.objection_trust')
            );
        }

        if ($this->isBuyIntent($msg)) {
            $response = $this->salesFlow->handleStage($state, 'sí confirmo', false);

            return $response ?? $this->withConfirmButtons($state, config('sales_copy.resume_checkout'));
        }

        return $this->withConfirmButtons($state, config('sales_copy.resume_checkout'));
    }

    /**
     * @return array{text: string, metadata: array}|null
     */
    protected function handleObjection(ConversationState $state, string $msg, string $stage): ?array
    {
        if (! $this->matchesObjection($msg, 'price')
            && ! $this->matchesObjection($msg, 'think')
            && ! $this->matchesObjection($msg, 'later')
        ) {
            return null;
        }

        $text = match (true) {
            $this->matchesObjection($msg, 'price') => config('sales_copy.objection_price'),
            $this->matchesObjection($msg, 'later') => config('sales_copy.objection_later'),
            default => config('sales_copy.objection_think'),
        };

        if ($stage === 'awaiting_size_selection' && ! $this->matchesObjection($msg, 'later')) {
            $sizes = $state->context['available_sizes'] ?? [];
            if (! empty($sizes)) {
                $text .= "\n\nTu talla disponible: ".implode(', ', array_map('strtoupper', $sizes));
            }
        }

        return [
            'text' => $this->business->applyBrandCta($text),
            'metadata' => [],
        ];
    }

    protected function matchesObjection(string $msg, string $type): bool
    {
        return match ($type) {
            'price' => (bool) preg_match('/\b(caro|carísimo|carisimo|costoso|muy caro|no tengo plata|presupuesto|más barato|mas barato)\b/u', $msg),
            'think' => (bool) preg_match('/\b(lo pienso|pienso|déjame pensar|dejame pensar|tal vez|quiz[aá]|no s[eé])\b/u', $msg),
            'later' => (bool) preg_match('/\b(despu[eé]s|m[aá]s tarde|ahora no|otro d[ií]a|mañana)\b/u', $msg),
            'trust' => (bool) preg_match('/\b(confianza|estafa|seguro|confiable|pago seguro)\b/u', $msg),
            default => false,
        };
    }

    /**
     * @return array{text: string, metadata: array}
     */
    protected function withConfirmButtons(ConversationState $state, string $text): array
    {
        $productName = (string) ($state->context['current_product_name'] ?? 'tu vestido');
        $fullText = $text."\n\n".config('sales_copy.order_confirm_intro')."\n{$productName}";

        $this->tools->executeSendInteractiveButtons(
            $state,
            $fullText,
            [
                ['id' => 'confirm_order', 'title' => 'Sí, quiero'],
                ['id' => 'escalate_human', 'title' => 'Hablar asesor'],
            ],
            'Confirmar pedido'
        );

        return ['text' => $this->business->applyBrandCta($fullText), 'metadata' => []];
    }

    public function orderConfirmationText(ConversationState $state): string
    {
        $productId = (int) ($state->context['current_product_id'] ?? 0);
        $size = (string) ($state->context['current_size'] ?? '');
        $color = (string) ($state->context['current_color'] ?? '');
        $product = $productId > 0 ? Product::find($productId) : null;

        $lines = [config('sales_copy.after_size_selected'), ''];

        if ($product) {
            $validation = PriceValidatorService::validateProductPrice($product);
            $price = number_format((float) ($validation['final_price'] ?? 0), 0);
            $lines[] = "✨ {$product->name} — S/ {$price}";
        }

        if ($color !== '') {
            $lines[] = "🎨 Color: {$color}";
        }
        if ($size !== '') {
            $lines[] = "📏 Talla: ".strtoupper($size);
        }

        $lines[] = '';
        $lines[] = $this->business->orderConfirmationPrompt();

        return implode("\n", $lines);
    }
}
