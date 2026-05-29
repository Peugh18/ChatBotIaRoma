<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\VectorSimilarity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Etapa 2: imagen del cliente → hasta 3 coincidencias del catálogo (BD).
 * 
 * Flujo:
 * 1. Intentar match por embeddings CLIP (si HF token + variantes indexadas)
 * 2. Si score < umbral o sin token HF → fallback a Groq vision + text search
 * 3. Mostrar 1 match claro, 2-3 opciones, o 0 matches
 */
class CatalogImageMatcherService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected LlmService $llmService,
        protected ProductPresentationService $presentation,
        protected BusinessConfigService $business,
        protected ImageEmbeddingService $embeddingService
    ) {
    }

    /**
     * @return array{text: string, metadata: array, matched: bool}
     */
    public function matchFromImage(ConversationState $state, string $imageUrl, ?string $userText = null): array
    {
        // Intentar match por embeddings CLIP primero
        if (config('catalog-vision.enabled')) {
            $clipMatches = $this->matchByEmbedding($imageUrl);
            if (!empty($clipMatches)) {
                return $this->handleMatches($state, $clipMatches, $userText);
            }
        }

        // Fallback: Groq vision + text search (flujo original)
        Log::info('CatalogImageMatcher: Falling back to Groq vision + text search');
        $colorOverride = $this->extractColorFromText($userText);
        $searchQuery = $this->describeImageForSearch($imageUrl, $userText);

        if ($searchQuery === '') {
            return ['text' => '', 'metadata' => [], 'matched' => false];
        }

        $result = $this->tools->executeGetProducts($state, $searchQuery, $colorOverride);
        $count = (int) ($result['count'] ?? 0);

        if ($count === 0) {
            return ['text' => '', 'metadata' => [], 'matched' => false];
        }

        if ($count === 1) {
            $product = Product::with('variants')->find((int) $result['products'][0]['id']);
            if (!$product) {
                return ['text' => '', 'metadata' => [], 'matched' => false];
            }

            if ($colorOverride) {
                $ctx = $state->context ?? [];
                $ctx['image_color_preference'] = $colorOverride;
                $state->context = $ctx;
                $state->save();
            }

            $response = $this->presentation->presentProductPick($state, $product->id);
            $response['matched'] = true;

            return $response;
        }

        $products = array_slice($result['products'] ?? [], 0, 3);
        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_product_selection';
        $ctx['last_shown_products'] = array_map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'final_price' => $p['final_price'] ?? null,
        ], $products);
        $state->context = $ctx;
        $state->save();

        $lines = ["✨ Hermosa, encontramos estos modelos parecidos:", ''];
        foreach ($products as $i => $p) {
            $lines[] = ($i + 1) . '. ' . $p['name'];
        }
        $lines[] = '';
        $lines[] = '¿Cuál deseas consultar? 💕';

        $text = implode("\n", $lines);
        $buttons = [];
        foreach ($products as $p) {
            $buttons[] = [
                'id' => 'pick_product_' . $p['id'],
                'title' => mb_substr((string) $p['name'], 0, 20),
            ];
        }

        if (!empty($buttons)) {
            $this->tools->executeSendInteractiveButtons($state, $text, array_slice($buttons, 0, 3), 'Elige modelo');
        }

        return [
            'text' => $this->business->applyBrandCta($text),
            'metadata' => [],
            'matched' => true,
        ];
    }

    /**
     * Match image by CLIP embedding similarity.
     *
     * @param  string  $imageUrl
     * @return array<array{variant_id: int, product_id: int, product_name: string, color: string, score: float, final_price: float}>
     */
    private function matchByEmbedding(string $imageUrl): array
    {
        try {
            // Get embedding for inbound image
            $queryEmbedding = $this->embeddingService->getEmbedding($imageUrl);
            if (!$queryEmbedding) {
                Log::warning('CatalogImageMatcher: Failed to get embedding for inbound image');
                return [];
            }

            // Get indexed variants with embeddings (cached for 1 hour)
            $variants = \Illuminate\Support\Facades\Cache::remember('catalog:indexed-variants', 3600, function () {
                return ProductVariant::query()
                    ->whereNotNull('embedding')
                    ->with('product')
                    ->get();
            });

            if ($variants->isEmpty()) {
                Log::info('CatalogImageMatcher: No indexed variants found');
                return [];
            }

            // Calculate similarity for each variant
            $matches = [];
            $minSimilarity = config('catalog-vision.min_similarity', 0.72);

            foreach ($variants as $variant) {
                $embedding = $variant->embedding;
                if (!is_array($embedding) || empty($embedding)) {
                    continue;
                }

                $score = VectorSimilarity::cosineSimilarity($queryEmbedding, $embedding);

                if ($score >= $minSimilarity) {
                    $matches[] = [
                        'variant_id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'product_name' => $variant->product->name,
                        'color' => $variant->color,
                        'score' => $score,
                        'final_price' => $variant->product->price - ($variant->product->discount ?? 0),
                    ];
                }
            }

            // Un producto = una opción (mejor variant por score)
            $byProduct = [];
            foreach ($matches as $match) {
                $pid = $match['product_id'];
                if (! isset($byProduct[$pid]) || $match['score'] > $byProduct[$pid]['score']) {
                    $byProduct[$pid] = $match;
                }
            }
            $matches = array_values($byProduct);

            usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);
            $topK = config('catalog-vision.top_k', 3);
            $matches = array_slice($matches, 0, $topK);

            if (!empty($matches)) {
                Log::info('CatalogImageMatcher: Found matches by embedding', [
                    'count' => count($matches),
                    'top_score' => $matches[0]['score'],
                ]);
            }

            return $matches;
        } catch (\Exception $e) {
            Log::error('CatalogImageMatcher: Error in matchByEmbedding', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Handle matches from embedding or text search.
     *
     * @param  ConversationState  $state
     * @param  array  $matches
     * @param  string|null  $userText
     * @return array{text: string, metadata: array, matched: bool}
     */
    private function handleMatches(ConversationState $state, array $matches, ?string $userText = null): array
    {
        if (empty($matches)) {
            return ['text' => '', 'metadata' => [], 'matched' => false];
        }

        // Extract color preference from best match if available
        $colorPreference = $matches[0]['color'] ?? $this->extractColorFromText($userText);

        // Single clear match
        if (count($matches) === 1) {
            $match = $matches[0];
            $product = Product::find($match['product_id']);
            if (!$product) {
                return ['text' => '', 'metadata' => [], 'matched' => false];
            }

            // Log single match found
            Log::info('CatalogImageMatcher: Single match found', [
                'variant_id' => $match['variant_id'] ?? null,
                'product_id' => $match['product_id'],
                'product_name' => $match['product_name'],
                'color' => $match['color'],
                'score' => $match['score'] ?? null,
                'conversation_id' => $state->conversation_id,
            ]);

            if ($colorPreference) {
                $ctx = $state->context ?? [];
                $ctx['image_color_preference'] = $colorPreference;
                $state->context = $ctx;
                $state->save();
            }

            $response = $this->presentation->presentProductPick($state, $product->id);
            $response['matched'] = true;

            return $response;
        }

        // Multiple matches: show selection
        Log::info('CatalogImageMatcher: Multiple matches found', [
            'count' => count($matches),
            'top_score' => $matches[0]['score'] ?? null,
            'product_ids' => array_map(fn ($m) => $m['product_id'], $matches),
            'conversation_id' => $state->conversation_id,
        ]);

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_product_selection';
        $ctx['last_shown_products'] = array_map(fn ($m) => [
            'id' => $m['product_id'],
            'name' => $m['product_name'],
            'final_price' => $m['final_price'],
        ], $matches);
        $state->context = $ctx;
        $state->save();

        $lines = ["✨ Hermosa, encontramos estos modelos parecidos:", ''];
        foreach ($matches as $i => $m) {
            $lines[] = ($i + 1) . '. ' . $m['product_name'];
        }
        $lines[] = '';
        $lines[] = '¿Cuál deseas consultar? 💕';

        $text = implode("\n", $lines);
        $buttons = [];
        foreach ($matches as $m) {
            $buttons[] = [
                'id' => 'pick_product_' . $m['product_id'],
                'title' => mb_substr((string) $m['product_name'], 0, 20),
            ];
        }

        if (!empty($buttons)) {
            $this->tools->executeSendInteractiveButtons($state, $text, array_slice($buttons, 0, 3), 'Elige modelo');
        }

        return [
            'text' => $this->business->applyBrandCta($text),
            'metadata' => [],
            'matched' => true,
        ];
    }

    protected function describeImageForSearch(string $imageUrl, ?string $userText): string
    {
        $settings = $this->llmService->getSettings();
        $apiKey = $settings->groq_api_key ?: env('GROQ_API_KEY');
        if (!$apiKey) {
            return trim((string) $userText);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $settings->model_vision ?: 'meta-llama/llama-4-scout-17b-16e-instruct',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres experto en moda femenina. Responde SOLO JSON: {"product_name":"","color":"","keywords":""}',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'Identifica tipo de vestido, color visible y palabras clave para buscar en catálogo.'],
                                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                            ],
                        ],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 120,
                ]);

            if (!$response->successful()) {
                Log::warning('CatalogImageMatcher: vision API failed', ['status' => $response->status()]);

                return trim((string) $userText);
            }

            $content = $response->json('choices.0.message.content') ?? '';
            $json = json_decode($content, true);
            if (!is_array($json) && preg_match('/\{.*\}/s', $content, $m)) {
                $json = json_decode($m[0], true);
            }

            if (!is_array($json)) {
                return trim((string) $userText);
            }

            $parts = array_filter([
                $json['product_name'] ?? null,
                $json['keywords'] ?? null,
                $userText,
            ]);

            return trim(implode(' ', $parts));
        } catch (\Throwable $e) {
            Log::warning('CatalogImageMatcher: exception', ['error' => $e->getMessage()]);

            return trim((string) $userText);
        }
    }

    protected function extractColorFromText(?string $text): ?string
    {
        if (!$text) {
            return null;
        }
        if (preg_match('/\b(borgoña|borgona|rojo|azul|verde|negro|blanco|rosa|lila|morado|beige|nude|celeste|fucsia)\b/iu', $text, $m)) {
            return mb_strtolower($m[1]);
        }
        if (preg_match('/\bcolor\s+([a-záéíóúñ]+)/iu', $text, $m)) {
            return mb_strtolower($m[1]);
        }

        return null;
    }
}
