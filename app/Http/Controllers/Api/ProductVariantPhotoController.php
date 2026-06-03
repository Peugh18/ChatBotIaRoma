<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IndexVariantEmbeddingJob;
use App\Models\ProductVariant;
use App\Services\ServicioMediaProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantPhotoController extends Controller
{
    public function store(Request $request, ProductVariant $variant, ServicioMediaProducto $media): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $path = $media->storeVariantPhoto($variant, $request->file('photo'));

        IndexVariantEmbeddingJob::dispatch($variant->id);

        return response()->json([
            'message' => 'Foto guardada',
            'image_path' => $path,
            'public_url' => $media->resolvePublicUrl($variant->fresh()),
        ]);
    }
}
