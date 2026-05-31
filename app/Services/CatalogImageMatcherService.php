<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Etapa 2: imagen del cliente → hasta 3 coincidencias del catálogo (BD).
 */
class CatalogImageMatcherService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected LlmService $llmService,
        protected ProductPresentationService $presentation,
        protected BusinessConfigService $business
    ) {
    }

    /**
     * @return array{text: string, metadata: array, matched: bool}
     */
    public function matchFromImage(ConversationState $state, string $imageUrl, ?string $userText = null): array
    {
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

    protected function describeImageForSearch(string $imageUrl, ?string $userText): string
    {
        $settings = $this->llmService->getSettings();
        $apiKey = $settings->groq_api_key ?: env('GROQ_API_KEY');
        if (!$apiKey) {
            return trim((string) $userText);
        }

        $finalImageUrl = $imageUrl;
        if (str_contains((string)$imageUrl, 'lookaside.fbsbx.com') || str_contains((string)$imageUrl, 'graph.facebook.com')) {
            $waToken = config('services.roma.wa_token');
            if ($waToken) {
                $imgRes = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $waToken,
                    'User-Agent' => 'curl/7.68.0'
                ])->get($imageUrl);
                
                if ($imgRes->successful()) {
                    $type = $imgRes->header('Content-Type') ?? 'image/jpeg';
                    $base64 = base64_encode($imgRes->body());
                    $finalImageUrl = "data:{$type};base64,{$base64}";
                } else {
                    \Illuminate\Support\Facades\Log::warning('CatalogImageMatcher: falló descarga de imagen de Meta', ['status' => $imgRes->status()]);
                }
            }
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
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
                                ['type' => 'image_url', 'image_url' => ['url' => $finalImageUrl]],
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
