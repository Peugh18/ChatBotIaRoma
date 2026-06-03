<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with(['category', 'variants'])->get();
        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'nullable|string|in:disponible,agotado,oculto',
            'tags_ia' => 'nullable|array',
            'variants' => 'required|array',
            'variants.*.color' => 'required|string',
            'variants.*.image_url' => 'nullable|string',
            'variants.*.sizes_stock' => 'required|array',
        ]);

        // Validación estricta: descuento no puede ser mayor que precio
        if (isset($validated['discount']) && $validated['discount'] > $validated['price']) {
            return response()->json([
                'message' => 'El descuento no puede ser mayor que el precio',
            ], 422);
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'discount' => $validated['discount'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'status' => $validated['status'] ?? Product::ESTADO_DISPONIBLE,
            'tags_ia' => $validated['tags_ia'] ?? [],
        ]);

        foreach ($validated['variants'] as $variant) {
            $product->variants()->create([
                'color' => $variant['color'],
                'image_url' => $variant['image_url'] ?? null,
                'sizes_stock' => $variant['sizes_stock'],
            ]);
        }

        return response()->json($product->load('variants'), 201);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'variants'])->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|string|in:disponible,agotado,oculto',
            'tags_ia' => 'nullable|array',
            'variants' => 'sometimes|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.color' => 'required|string',
            'variants.*.image_url' => 'nullable|string',
            'variants.*.sizes_stock' => 'required|array',
        ]);

        $newPrice = $validated['price'] ?? $product->price;
        $newDiscount = $validated['discount'] ?? $product->discount;

        // Validación estricta: descuento no puede ser mayor que precio
        if ($newDiscount && $newDiscount > $newPrice) {
            return response()->json([
                'message' => 'El descuento no puede ser mayor que el precio',
            ], 422);
        }

        $product->update([
            'name' => $validated['name'] ?? $product->name,
            'description' => $validated['description'] ?? $product->description,
            'price' => $newPrice,
            'discount' => $validated['discount'] ?? $product->discount,
            'category_id' => $validated['category_id'] ?? $product->category_id,
            'status' => $validated['status'] ?? $product->status ?? Product::ESTADO_DISPONIBLE,
            'tags_ia' => $validated['tags_ia'] ?? $product->tags_ia,
        ]);

        if (isset($validated['variants'])) {
            $keptIds = [];

            foreach ($validated['variants'] as $variantData) {
                $payload = [
                    'color' => $variantData['color'],
                    'image_url' => $variantData['image_url'] ?? null,
                    'sizes_stock' => $variantData['sizes_stock'],
                ];

                if (! empty($variantData['id'])) {
                    $existing = $product->variants()->find($variantData['id']);
                    if ($existing) {
                        // No sobrescribir image_path (foto subida por archivo)
                        if ($existing->image_path) {
                            unset($payload['image_url']);
                        }
                        $existing->update($payload);
                        $keptIds[] = $existing->id;

                        continue;
                    }
                }

                $created = $product->variants()->create($payload);
                $keptIds[] = $created->id;
            }

            $removed = $product->variants()->whereNotIn('id', $keptIds)->get();
            foreach ($removed as $variant) {
                if ($variant->image_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($variant->image_path);
                }
                $variant->delete();
            }
        }

        return response()->json($product->load('variants'));
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(null, 204);
    }
}
