<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Product;

/**
 * Presenta hasta 3 opciones de producto tras match por foto (CLIP o texto):
 * fotos numeradas + lista + botones pick_product_{id}.
 */
class CatalogMatchPresenterService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected BusinessConfigService $business
    ) {
    }

    /**
     * @param  array<int, array{id: int, name: string, final_price?: float|null}>  $products
     * @return array{text: string, metadata: array, matched: bool}
     */
    public function presentProductOptions(ConversationState $state, array $products): array
    {
        $products = array_slice($products, 0, 3);
        if ($products === []) {
            return ['text' => '', 'metadata' => [], 'matched' => false];
        }

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_product_selection';
        $ctx['last_shown_products'] = array_map(fn ($p) => [
            'id' => (int) $p['id'],
            'name' => (string) $p['name'],
            'final_price' => $p['final_price'] ?? null,
        ], $products);
        $state->context = $ctx;
        $state->save();

        $lines = [
            config('sales_copy.live_multi_match_intro'),
            '',
        ];
        $buttons = [];

        foreach ($products as $i => $p) {
            $productId = (int) $p['id'];
            $name = (string) $p['name'];
            $price = $this->resolvePrice($productId, $p['final_price'] ?? null);
            $num = $i + 1;
            $priceLabel = $price > 0 ? ' — S/ '.number_format($price, 0) : '';

            $lines[] = $num.'. '.$name.$priceLabel;

            $this->tools->enqueueProductImage(
                $state,
                $productId,
                null,
                $num.'. '.$name.$priceLabel.' 📸'
            );

            $buttons[] = [
                'id' => 'pick_product_'.$productId,
                'title' => mb_substr($name, 0, 20),
            ];
        }

        $lines[] = '';
        $lines[] = config('sales_copy.live_multi_match_footer');

        $text = implode("\n", $lines);

        if ($buttons !== []) {
            $this->tools->executeSendInteractiveButtons($state, $text, $buttons, 'Elige modelo');
        }

        return [
            'text' => $this->business->applyBrandCta($text),
            'metadata' => [],
            'matched' => true,
        ];
    }

    private function resolvePrice(int $productId, mixed $fallback): float
    {
        if (is_numeric($fallback) && (float) $fallback > 0) {
            return (float) $fallback;
        }

        $product = Product::find($productId);
        if (! $product) {
            return 0.0;
        }

        $validation = PriceValidatorService::validateProductPrice($product);

        return (float) ($validation['final_price'] ?? 0);
    }
}
