<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Embeddings de imagen vía Voyage multimodal (image-image retrieval).
 */
class ServicioEmbeddingImagen
{
    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 1000;

    /**
     * @param  'query'|'document'  $inputType
     * @return array<float>|null
     */
    public function getEmbedding(string $imageSource, string $inputType = 'document'): ?array
    {
        try {
            $contentItem = $this->buildImageContentItem($imageSource);
            if ($contentItem === null) {
                Log::warning('ServicioEmbeddingImagen: Failed to load image', ['source' => $imageSource]);

                return null;
            }

            return $this->callVoyageApi([$contentItem], $inputType);
        } catch (\Exception $e) {
            Log::error('ServicioEmbeddingImagen: Error getting embedding', [
                'source' => $imageSource,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function buildImageContentItem(string $source): ?array
    {
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            if ($this->isCrmStorageUrl($source)) {
                $relative = $this->crmStorageUrlToRelativePath($source);
                if ($relative !== null) {
                    $dataUrl = $this->loadImageFromStorageAsDataUrl($relative);

                    return $dataUrl !== null
                        ? ['type' => 'image_base64', 'image_base64' => $dataUrl]
                        : null;
                }
            }

            if ($this->isProtectedMetaUrl($source) || $this->isRomaApiMediaUrl($source)) {
                $dataUrl = $this->downloadUrlAsDataUrl($source);

                return $dataUrl !== null
                    ? ['type' => 'image_base64', 'image_base64' => $dataUrl]
                    : null;
            }

            return ['type' => 'image_url', 'image_url' => $source];
        }

        $dataUrl = $this->loadImageFromStorageAsDataUrl($source);

        return $dataUrl !== null
            ? ['type' => 'image_base64', 'image_base64' => $dataUrl]
            : null;
    }

    private function loadImageFromStorageAsDataUrl(string $path): ?string
    {
        try {
            foreach (['public', 'local'] as $diskName) {
                $disk = Storage::disk($diskName);
                if (! $disk->exists($path)) {
                    continue;
                }

                $bytes = $disk->get($path);
                $mime = $this->guessMimeType($path, $bytes);

                return 'data:'.$mime.';base64,'.base64_encode($bytes);
            }

            Log::warning('ServicioEmbeddingImagen: File not found', ['path' => $path]);

            return null;
        } catch (\Exception $e) {
            Log::error('ServicioEmbeddingImagen: Error reading file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function guessMimeType(string $path, string $bytes): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $byExtension = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    return $detected;
                }
            }
        }

        return $byExtension;
    }

    /**
     * @param  array<int, array<string, string>>  $content
     * @param  'query'|'document'  $inputType
     * @return array<float>|null
     */
    private function callVoyageApi(array $content, string $inputType): ?array
    {
        $apiKey = $this->resolveApiKey();
        if ($apiKey === null || $apiKey === '') {
            Log::warning('ServicioEmbeddingImagen: No Voyage API key configured');

            return null;
        }

        $model = (string) config('catalog-vision.voyage_model', 'voyage-multimodal-3.5');
        $url = (string) config('catalog-vision.voyage_api_url', 'https://api.voyageai.com/v1/multimodalembeddings');

        $body = [
            'model' => $model,
            'input_type' => $inputType,
            'truncation' => true,
            'inputs' => [
                ['content' => $content],
            ],
        ];

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(90)
                    ->acceptJson()
                    ->post($url, $body);

                if ($response->status() === 429 && $attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * $attempt * 1000);

                    continue;
                }

                if (! $response->successful()) {
                    Log::warning('ServicioEmbeddingImagen: Voyage API error', [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 400),
                        'model' => $model,
                        'input_type' => $inputType,
                    ]);

                    if ($attempt < self::MAX_RETRIES) {
                        usleep(self::RETRY_DELAY_MS * $attempt * 1000);
                    }

                    continue;
                }

                $embedding = $this->parseVoyageResponse($response->json());
                if ($embedding !== null) {
                    Log::info('ServicioEmbeddingImagen: Successfully generated embedding', [
                        'model' => $model,
                        'input_type' => $inputType,
                        'dimension' => count($embedding),
                    ]);

                    return $embedding;
                }
            } catch (\Exception $e) {
                Log::error('ServicioEmbeddingImagen: Voyage request error', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * $attempt * 1000);
            }
        }

        return null;
    }

    /**
     * @param  mixed  $data
     * @return array<float>|null
     */
    private function parseVoyageResponse(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $rows = $data['data'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $embedding = $rows[0]['embedding'] ?? null;
        if (! is_array($embedding) || $embedding === []) {
            return null;
        }

        $flat = array_map('floatval', $embedding);
        $dimension = count($flat);
        if ($dimension < 64 || $dimension > 4096) {
            Log::warning('ServicioEmbeddingImagen: Unexpected embedding dimension', [
                'dimension' => $dimension,
            ]);

            return null;
        }

        return $flat;
    }

    public function resolveApiKey(): ?string
    {
        $key = config('catalog-vision.voyage_api_key')
            ?? \App\Models\BotSetting::first()?->voyage_api_key;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    private function isProtectedMetaUrl(string $url): bool
    {
        return str_contains($url, 'lookaside.fbsbx.com')
            || str_contains($url, 'graph.facebook.com');
    }

    private function isRomaApiMediaUrl(string $url): bool
    {
        return str_contains($url, '/inbound-media/')
            || str_contains($url, '/api/media/file/')
            || str_contains($url, 'ngrok-free.dev');
    }

    private function isCrmStorageUrl(string $url): bool
    {
        return str_contains($url, '/storage/');
    }

    private function crmStorageUrlToRelativePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $relative = ltrim(str_replace('/storage/', '', $path), '/');

        return $relative !== '' ? $relative : null;
    }

    private function downloadUrlAsDataUrl(string $url): ?string
    {
        $wa = app(ServicioDescargaImagenWhatsapp::class);
        $localUrl = $wa->esUrlMeta($url)
            ? $wa->descargarAMediaPublica($url)
            : ($wa->esUrlRomaApiPublica($url) ? $wa->descargarDesdeUrlPublica($url) : null);

        if ($localUrl === null) {
            return null;
        }

        $relative = $this->crmStorageUrlToRelativePath($localUrl);
        if ($relative === null) {
            return null;
        }

        return $this->loadImageFromStorageAsDataUrl($relative);
    }
}
