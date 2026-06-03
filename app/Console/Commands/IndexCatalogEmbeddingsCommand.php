<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Services\ServicioEmbeddingImagen;
use App\Services\ServicioMediaProducto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IndexCatalogEmbeddingsCommand extends Command
{
    protected $signature = 'catalog:index-embeddings {--force : Force re-indexing all variants}';

    protected $description = 'Index product variant embeddings using Voyage multimodal';

    public function handle(ServicioEmbeddingImagen $embeddingService, ServicioMediaProducto $media): int
    {
        $force = $this->option('force');
        
        $query = ProductVariant::query()
            ->where(function ($q) {
                $q->whereNotNull('image_path')
                  ->orWhereNotNull('image_url');
            });

        if (!$force) {
            $query->whereNull('embedding');
        }

        $variants = $query->get();
        $total = $variants->count();

        if ($total === 0) {
            $this->info('No variants to index.');
            return 0;
        }

        $this->info("Indexing {$total} variants...");

        $indexed = 0;
        $failed = 0;

        foreach ($variants as $variant) {
            try {
                $imageSource = $media->resolveEmbeddingSource($variant);
                if (!$imageSource) {
                    $failed++;
                    continue;
                }

                $embedding = $embeddingService->getEmbedding($imageSource, 'document');
                if ($embedding) {
                    $variant->update([
                        'embedding' => $embedding,
                        'embedding_indexed_at' => now(),
                        'embedding_model' => config('catalog-vision.voyage_model'),
                    ]);
                    $indexed++;
                    $this->line("✓ Indexed variant {$variant->id}");
                } else {
                    $failed++;
                    $this->line("✗ Failed to get embedding for variant {$variant->id}");
                }

                usleep(config('catalog-vision.index_sleep_ms', 1000) * 1000);
            } catch (\Exception $e) {
                $failed++;
                Log::error('IndexCatalogEmbeddings: Error', [
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
                $this->line("✗ Error indexing variant {$variant->id}: {$e->getMessage()}");
            }
        }

        Cache::forget('catalog:indexed-variants');

        $this->info("Done! Indexed: {$indexed}, Failed: {$failed}");

        return 0;
    }
}
