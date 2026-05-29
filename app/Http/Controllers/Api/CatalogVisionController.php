<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageEmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller para testing y diagnóstico del reconocimiento visual CLIP.
 */
class CatalogVisionController extends Controller
{
    public function __construct(
        private ImageEmbeddingService $embeddingService
    ) {
    }

    /**
     * Testea que Hugging Face API esté configurada y respondiendo.
     *
     * POST /api/test-embedding
     *
     * Body: { "image_url": "https://example.com/image.jpg" }
     *
     * Response:
     * {
     *   "success": true,
     *   "dimension": 768,
     *   "sample": [0.1, 0.2, ...],
     *   "model": "openai/clip-vit-large-patch14"
     * }
     */
    public function testEmbedding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url' => 'required|url',
        ]);

        $imageUrl = $validated['image_url'];

        Log::info('CatalogVisionController: Testing embedding', [
            'image_url' => $imageUrl,
            'model' => config('catalog-vision.clip_model'),
        ]);

        $embedding = $this->embeddingService->getEmbedding($imageUrl);

        if ($embedding === null) {
            Log::warning('CatalogVisionController: Embedding generation failed', [
                'image_url' => $imageUrl,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el embedding. Verifica que HUGGINGFACE_TOKEN esté configurado en .env o en bot-settings.',
                'token_configured' => config('catalog-vision.huggingface_token') !== null,
                'model' => config('catalog-vision.clip_model'),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'dimension' => count($embedding),
            'sample' => array_slice($embedding, 0, 5),
            'model' => config('catalog-vision.clip_model'),
            'min_similarity' => config('catalog-vision.min_similarity'),
            'top_k' => config('catalog-vision.top_k'),
        ]);
    }

    /**
     * Obtiene estadísticas del catálogo visual.
     *
     * GET /api/catalog-vision/stats
     *
     * Response:
     * {
     *   "total_variants": 50,
     *   "indexed_variants": 42,
     *   "indexed_percentage": 84,
     *   "model": "openai/clip-vit-large-patch14"
     * }
     */
    public function stats(): JsonResponse
    {
        $total = \App\Models\ProductVariant::query()
            ->whereNotNull('image_path')
            ->orWhereNotNull('image_url')
            ->count();

        $indexed = \App\Models\ProductVariant::query()
            ->whereNotNull('embedding')
            ->count();

        return response()->json([
            'total_variants_with_photo' => $total,
            'indexed_variants' => $indexed,
            'indexed_percentage' => $total > 0 ? round(($indexed / $total) * 100, 2) : 0,
            'model' => config('catalog-vision.clip_model'),
            'min_similarity' => config('catalog-vision.min_similarity'),
        ]);
    }
}
