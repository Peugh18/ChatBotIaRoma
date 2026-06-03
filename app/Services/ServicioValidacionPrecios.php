<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class ServicioValidacionPrecios
{
    /**
     * Valida que un producto tenga un precio correcto configurado.
     */
    public static function validateProductPrice(Product $product): array
    {
        if (!$product) {
            return [
                'valid' => false,
                'error' => 'Producto no encontrado',
                'final_price' => 0.00,
            ];
        }

        if ($product->price === null) {
            Log::error('PriceValidator: Producto sin precio configurado', ['product_id' => $product->id]);
            return [
                'valid' => false,
                'error' => "El producto '{$product->name}' no tiene precio configurado.",
                'final_price' => 0.00,
            ];
        }

        $basePrice = (float) $product->price;
        if ($basePrice <= 0) {
            Log::error('PriceValidator: Producto con precio inválido (0 o negativo)', [
                'product_id' => $product->id,
                'price' => $basePrice
            ]);
            return [
                'valid' => false,
                'error' => "El producto '{$product->name}' tiene un precio inválido.",
                'final_price' => 0.00,
            ];
        }

        $discount = (float) ($product->discount ?? 0);
        if ($discount > $basePrice) {
            Log::error('PriceValidator: Descuento mayor que precio', [
                'product_id' => $product->id,
                'price' => $basePrice,
                'discount' => $discount
            ]);
            return [
                'valid' => false,
                'error' => "El producto '{$product->name}' tiene un descuento inválido.",
                'final_price' => 0.00,
            ];
        }

        $finalPrice = $basePrice - $discount;
        if ($finalPrice <= 0) {
            return [
                'valid' => false,
                'error' => "Error de precio final en '{$product->name}'.",
                'final_price' => 0.00,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'final_price' => $finalPrice,
            'base_price' => $basePrice,
            'discount' => $discount,
        ];
    }

    /**
     * Valida múltiples productos para una orden.
     */
    public static function validateOrderItems(array $items): array
    {
        $errors = [];
        $validItems = [];
        $subtotal = 0.00;

        foreach ($items as $itemData) {
            $productId = $itemData['product_id'] ?? null;
            $qty = (int) ($itemData['qty'] ?? 1);

            if (!$productId) {
                $errors[] = 'Item sin ID de producto válido';
                continue;
            }

            $product = Product::find($productId);
            $validation = self::validateProductPrice($product);

            if (!$validation['valid']) {
                $errors[] = $validation['error'];
                continue;
            }

            $totalItem = $validation['final_price'] * $qty;
            $subtotal += $totalItem;

            $validItems[] = [
                'product' => $product,
                'qty' => $qty,
                'unit_price' => $validation['final_price'],
                'total' => $totalItem,
                'color' => $itemData['color'] ?? null,
                'size' => $itemData['size'] ?? null,
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'items' => $validItems,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Verifica la disponibilidad de stock en talla y color de la variante.
     */
    public static function validateStock(int $productId, string $color, string $size, int $requestedQty): array
    {
        $variant = ProductVariant::where('product_id', $productId)
            ->where('color', 'like', "%{$color}%")
            ->first();

        if (!$variant) {
            return [
                'available' => false,
                'stock' => 0,
                'error' => "Color '{$color}' no disponible para este vestido.",
            ];
        }

        $stock = $variant->sizes_stock ?? [];
        $availableStock = (int) ($stock[$size] ?? 0);

        if ($availableStock < $requestedQty) {
            return [
                'available' => false,
                'stock' => $availableStock,
                'error' => "Stock insuficiente: solo quedan {$availableStock} unidades en talla {$size}.",
            ];
        }

        return [
            'available' => true,
            'stock' => $availableStock,
            'error' => null,
        ];
    }
}
