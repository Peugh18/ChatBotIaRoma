<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\ProductMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantPhotoController extends Controller
{
    public function store(Request $request, ProductVariant $variant, ProductMediaService $media): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $path = $media->storeVariantPhoto($variant, $request->file('photo'));

        return response()->json([
            'message' => 'Foto guardada',
            'image_path' => $path,
            'public_url' => $media->resolvePublicUrl($variant->fresh()),
        ]);
    }
}
