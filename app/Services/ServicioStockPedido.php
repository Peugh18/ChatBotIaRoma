<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\NormalizadorStockTallas;
use Illuminate\Support\Facades\Log;

/**
 * Descuenta stock al confirmar pago (paid), no al crear pending ni al marcar enviado.
 */
class ServicioStockPedido
{
    public function descontarSiAplica(Order $order): bool
    {
        if ($order->stock_deducted_at !== null) {
            return false;
        }

        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            return false;
        }

        foreach ($order->items as $item) {
            $this->descontarLinea(
                (int) $item->product_id,
                (string) ($item->color ?? ''),
                (string) ($item->size ?? ''),
                max(1, (int) $item->qty)
            );
        }

        $order->update(['stock_deducted_at' => now()]);

        Log::info('ServicioStockPedido: stock descontado', ['order_id' => $order->id]);

        return true;
    }

    public function restaurarSiAplica(Order $order): bool
    {
        if ($order->stock_deducted_at === null) {
            return false;
        }

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            $this->restaurarLinea(
                (int) $item->product_id,
                (string) ($item->color ?? ''),
                (string) ($item->size ?? ''),
                max(1, (int) $item->qty)
            );
        }

        $order->update(['stock_deducted_at' => null]);

        Log::info('ServicioStockPedido: stock restaurado', ['order_id' => $order->id]);

        return true;
    }

    protected function descontarLinea(int $productoId, string $color, string $talla, int $qty): void
    {
        $variante = $this->variantePorColor($productoId, $color);
        if (! $variante) {
            return;
        }

        $stock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);
        $tallaNorm = mb_strtoupper(trim($talla));
        if ($tallaNorm === '' || ! isset($stock[$tallaNorm])) {
            return;
        }

        $stock[$tallaNorm] = max(0, (int) $stock[$tallaNorm] - $qty);
        $variante->update(['sizes_stock' => $stock]);
    }

    protected function restaurarLinea(int $productoId, string $color, string $talla, int $qty): void
    {
        $variante = $this->variantePorColor($productoId, $color);
        if (! $variante) {
            return;
        }

        $stock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);
        $tallaNorm = mb_strtoupper(trim($talla));
        if ($tallaNorm === '') {
            return;
        }

        $stock[$tallaNorm] = (int) ($stock[$tallaNorm] ?? 0) + $qty;
        $variante->update(['sizes_stock' => $stock]);
    }

    protected function variantePorColor(int $productoId, string $color): ?ProductVariant
    {
        $colorNorm = mb_strtolower(trim($color));
        if ($colorNorm === '') {
            return null;
        }

        return ProductVariant::query()
            ->where('product_id', $productoId)
            ->whereRaw('LOWER(color) = ?', [$colorNorm])
            ->first();
    }
}
