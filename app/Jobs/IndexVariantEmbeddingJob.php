<?php

namespace App\Jobs;

use App\Models\ProductVariant;
use App\Services\ServicioEmbeddingImagen;
use App\Services\ServicioMediaProducto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IndexVariantEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $variantId
    ) {
    }

    public function handle(ServicioEmbeddingImagen $embeddingService, ServicioMediaProducto $media): void
    {
        try {
            $variant = ProductVariant::find($this->variantId);

            if (! $variant) {
                Log::warning('IndexVariantEmbeddingJob: Variant not found', [
                    'variant_id' => $this->variantId,
                ]);

                return;
            }

            $imageSource = $media->resolveEmbeddingSource($variant);

            if (! $imageSource) {
                Log::warning('IndexVariantEmbeddingJob: No image source found', [
                    'variant_id' => $this->variantId,
                    'image_path' => $variant->image_path,
                    'file_exists' => $variant->image_path
                        ? \Illuminate\Support\Facades\Storage::disk('public')->exists($variant->image_path)
                        : false,
                ]);

                return;
            }

            // Get embedding from Voyage
            $embedding = $embeddingService->getEmbedding($imageSource, 'document');

            if (!$embedding) {
                Log::warning('IndexVariantEmbeddingJob: Failed to get embedding', [
                    'variant_id' => $this->variantId,
                    'image_source' => $imageSource,
                ]);
                return;
            }

            // Update variant with embedding
            $variant->update([
                'embedding' => $embedding,
                'embedding_indexed_at' => now(),
                'embedding_model' => config('catalog-vision.voyage_model'),
            ]);

            Cache::forget('catalog:indexed-variants');

            Log::info('IndexVariantEmbeddingJob: Successfully indexed variant', [
                'variant_id' => $this->variantId,
                'product_id' => $variant->product_id,
                'embedding_dimension' => count($embedding),
                'model' => config('catalog-vision.voyage_model'),
            ]);
        } catch (\Exception $e) {
            Log::error('IndexVariantEmbeddingJob: Error indexing variant', [
                'variant_id' => $this->variantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
