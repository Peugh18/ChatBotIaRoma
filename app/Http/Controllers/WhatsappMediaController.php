<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve fotos de producto por URL pública (Meta/WhatsApp debe poder descargarlas).
 */
class WhatsappMediaController extends Controller
{
    public function variant(ProductVariant $variant): BinaryFileResponse
    {
        $path = $variant->image_path;

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404, 'Imagen no encontrada');
        }

        $absolute = Storage::disk('public')->path($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
