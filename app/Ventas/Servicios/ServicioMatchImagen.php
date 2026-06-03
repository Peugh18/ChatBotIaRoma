<?php

namespace App\Ventas\Servicios;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ServicioDescargaImagenWhatsapp;
use App\Services\ServicioEmbeddingImagen;
use App\Support\SimilitudVectorial;
use App\Ventas\Repositorios\RepositorioCatalogo;
use Illuminate\Support\Collection;

class ServicioMatchImagen
{
    public function __construct(
        protected ServicioEmbeddingImagen $embeddings,
        protected RepositorioCatalogo $catalogo,
    ) {}

    /**
     * @return array{
     *   tipo: 'alto'|'medio'|'bajo'|'sin_config'|'sin_token_wa'|'sin_imagen',
     *   productos: Collection<int, Product>,
     *   mejor_score: float
     * }
     */
    public function buscarPorImagen(string $imageUrl): array
    {
        if (! $this->embeddings->isConfigured() || ! config('catalog-vision.enabled', true)) {
            return [
                'tipo' => 'sin_config',
                'productos' => collect(),
                'mejor_score' => 0.0,
            ];
        }

        $descarga = app(ServicioDescargaImagenWhatsapp::class);
        if ($descarga->esUrlMeta($imageUrl) && ! $descarga->esUrlRomaApiPublica($imageUrl) && ! $descarga->tokenConfigurado()) {
            return [
                'tipo' => 'sin_token_wa',
                'productos' => collect(),
                'mejor_score' => 0.0,
            ];
        }

        $imageUrl = $descarga->resolverParaProcesamiento($imageUrl) ?? $imageUrl;

        $query = $this->embeddings->getEmbedding($imageUrl, 'query');
        if ($query === null) {
            return [
                'tipo' => $descarga->esUrlMeta($imageUrl) ? 'sin_imagen' : 'bajo',
                'productos' => collect(),
                'mejor_score' => 0.0,
            ];
        }

        $variantes = ProductVariant::query()
            ->with('product.variants')
            ->whereNotNull('embedding')
            ->get();

        $scores = [];
        foreach ($variantes as $i => $variante) {
            $emb = $variante->embedding;
            if (! is_array($emb) || $emb === []) {
                continue;
            }
            $producto = $variante->product;
            if (! $producto || ! $this->catalogo->productoTieneStockVendible($producto)) {
                continue;
            }
            $score = SimilitudVectorial::cosineSimilarity($query, $emb);
            $pid = $producto->id;
            if (! isset($scores[$pid]) || $score > $scores[$pid]['score']) {
                $scores[$pid] = [
                    'product' => $producto,
                    'score' => $score,
                ];
            }
        }

        if ($scores === []) {
            return [
                'tipo' => 'bajo',
                'productos' => collect(),
                'mejor_score' => 0.0,
            ];
        }

        uasort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        $ordenados = array_values($scores);
        $mejor = (float) ($ordenados[0]['score'] ?? 0);

        $umbralAlto = (float) config('flujo_ventas.vision_match_alto', 0.85);
        $umbralMedio = (float) config('flujo_ventas.vision_match_medio', 0.72);

        $filtrados = array_filter(
            $ordenados,
            fn ($row) => $row['score'] >= $umbralMedio
        );

        $productos = collect(array_map(fn ($row) => $row['product'], array_slice($filtrados, 0, 3)));

        $tipo = match (true) {
            $mejor >= $umbralAlto => 'alto',
            $mejor >= $umbralMedio => 'medio',
            default => 'bajo',
        };

        if ($tipo === 'bajo' && $productos->isEmpty()) {
            $ref = $ordenados[0]['product'] ?? null;
            if ($ref instanceof Product) {
                $productos = $this->catalogo->similaresDe($ref, 3);
            }
        }

        return [
            'tipo' => $tipo,
            'productos' => $productos,
            'mejor_score' => $mejor,
        ];
    }
}
