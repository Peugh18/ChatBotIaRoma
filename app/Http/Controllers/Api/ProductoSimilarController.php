<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductoSimilar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoSimilarController extends Controller
{
    public function show(string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $manual = ProductoSimilar::query()
            ->where('product_id', $product->id)
            ->with('similarProduct.category')
            ->orderBy('orden')
            ->get()
            ->map(fn (ProductoSimilar $row) => [
                'id' => $row->similar_product_id,
                'name' => $row->similarProduct?->name,
                'price' => $row->similarProduct?->price,
                'status' => $row->similarProduct?->status,
                'orden' => $row->orden,
            ]);

        return response()->json([
            'product_id' => $product->id,
            'manual' => $manual,
        ]);
    }

    public function update(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'similar_product_ids' => 'present|array|max:10',
            'similar_product_ids.*' => 'integer|exists:products,id|distinct',
        ]);

        $ids = array_values(array_filter(
            array_map('intval', $validated['similar_product_ids']),
            fn (int $id) => $id !== (int) $product->id
        ));

        ProductoSimilar::query()->where('product_id', $product->id)->delete();

        foreach ($ids as $orden => $similarId) {
            ProductoSimilar::create([
                'product_id' => $product->id,
                'similar_product_id' => $similarId,
                'orden' => $orden,
            ]);
        }

        return $this->show((string) $product->id);
    }
}
