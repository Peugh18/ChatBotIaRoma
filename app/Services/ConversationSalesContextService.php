<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Order;
use App\Models\Product;

/**
 * Contexto de venta para el panel del asesor en Chat CRM.
 */
class ConversationSalesContextService
{
    public function __construct(
        protected ProductMediaService $media
    ) {
    }

    public function forPhone(string $phone): array
    {
        $state = ConversationState::where('phone_number', $phone)->first();
        $ctx = $state?->context ?? [];

        $productId = (int) ($ctx['current_product_id'] ?? 0);
        $colors = [];
        $currentProduct = null;

        if ($productId > 0) {
            $product = Product::with('variants')->find($productId);
            if ($product) {
                $price = (float) $product->price - (float) ($product->discount ?? 0);
                $currentProduct = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $price,
                    'selected_color' => $ctx['current_color'] ?? null,
                    'selected_size' => $ctx['current_size'] ?? null,
                ];
                $colors = $this->enrichColors($product);
            }
        }

        $recent = [];
        foreach ($ctx['last_shown_products'] ?? [] as $item) {
            $pid = (int) ($item['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $p = Product::with('variants')->find($pid);
            if (!$p) {
                continue;
            }
            $recent[] = [
                'id' => $p->id,
                'name' => $p->name,
                'final_price' => (float) ($item['final_price'] ?? $p->price),
                'thumbnail' => $this->firstVariantImage($p),
                'colors' => $this->enrichColors($p),
            ];
        }

        $featured = Product::with('variants')
            ->whereHas('variants')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'final_price' => (float) $p->price - (float) ($p->discount ?? 0),
                'thumbnail' => $this->firstVariantImage($p),
            ])
            ->values()
            ->all();

        $orderId = (int) ($ctx['last_order_id'] ?? 0);
        $pendingOrder = $orderId > 0 ? Order::find($orderId) : null;

        return [
            'phone' => $phone,
            'sales_stage' => $ctx['sales_stage'] ?? null,
            'handoff' => $ctx['handoff'] ?? null,
            'current_product' => $currentProduct,
            'colors' => $colors,
            'recent_products' => $recent,
            'featured_products' => $featured,
            'payment_validation' => [
                'pending' => ($ctx['sales_stage'] ?? null) === 'awaiting_payment_validation',
                'order_id' => $orderId > 0 ? $orderId : null,
                'order_status' => $pendingOrder?->status,
                'order_total' => $pendingOrder ? (float) $pendingOrder->amount_total : null,
                'payment_proof_url' => $ctx['payment_proof_url'] ?? $pendingOrder?->payment_proof_url,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function enrichColors(Product $product): array
    {
        $rows = [];
        foreach ($this->media->colorsForProduct($product) as $row) {
            $variant = $product->variants->first(fn ($v) => mb_strtolower($v->color) === mb_strtolower($row['color']));
            $stock = $variant?->sizes_stock ?? [];
            $parts = [];
            if (is_array($stock)) {
                foreach ($stock as $size => $qty) {
                    if ((int) $qty > 0) {
                        $parts[] = "{$size}:{$qty}";
                    }
                }
            }

            $rows[] = [
                'color' => $row['color'],
                'image_url' => $row['image_url'],
                'has_stock' => $row['has_stock'],
                'stock_summary' => empty($parts) ? 'Sin stock' : implode(', ', $parts),
            ];
        }

        return $rows;
    }

    protected function firstVariantImage(Product $product): ?string
    {
        foreach ($product->variants as $variant) {
            $url = $this->media->resolvePublicUrl($variant);
            if ($url) {
                return $url;
            }
        }

        return null;
    }
}
