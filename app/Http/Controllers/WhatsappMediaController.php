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

    public function proxy(\Illuminate\Http\Request $request)
    {
        $url = $request->query('url');
        if (!$url) {
            abort(400, 'URL de imagen requerida');
        }

        $token = config('services.roma.wa_token');

        if (!str_contains((string)$url, 'lookaside.fbsbx.com') && !str_contains((string)$url, 'graph.facebook.com')) {
            return redirect($url);
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'curl/7.68.0'
        ])->get($url);

        if ($response->successful()) {
            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type') ?? 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response()->json([
            'error' => 'No se pudo obtener la imagen desde WhatsApp',
            'status' => $response->status(),
            'url' => $url,
            'roma_response' => $response->body()
        ], $response->status());
    }
}
