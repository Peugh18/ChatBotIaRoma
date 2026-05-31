<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating image embeddings via Hugging Face CLIP API.
 */
class ImageEmbeddingService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    public function __construct()
    {
    }

    /**
     * Get embedding for an image from URL or local path.
     *
     * @param  string  $imageSource  URL or local file path
     * @return array<float>|null  Embedding vector or null on failure
     */
    public function getEmbedding(string $imageSource): ?array
    {
        try {
            $imageData = $this->loadImage($imageSource);
            if (!$imageData) {
                Log::warning('ImageEmbeddingService: Failed to load image', ['source' => $imageSource]);
                return null;
            }

            return $this->callHuggingFaceAPI($imageData);
        } catch (\Exception $e) {
            Log::error('ImageEmbeddingService: Error getting embedding', [
                'source' => $imageSource,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Load image from URL or local storage.
     *
     * @param  string  $source  URL or local path
     * @return string|null  Base64 encoded image data or null
     */
    private function loadImage(string $source): ?string
    {
        try {
            if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
                return $this->loadImageFromUrl($source);
            } else {
                return $this->loadImageFromStorage($source);
            }
        } catch (\Exception $e) {
            Log::error('ImageEmbeddingService: Failed to load image', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Load image from HTTP URL.
     *
     * @param  string  $url
     * @return string|null  Base64 encoded image data
     */
    private function loadImageFromUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->get($url);

            if (!$response->successful()) {
                Log::warning('ImageEmbeddingService: Failed to download image', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Validar tamaño de imagen (máximo 10MB)
            $imageData = $response->body();
            $size = strlen($imageData);
            if ($size > 10 * 1024 * 1024) {
                Log::warning('ImageEmbeddingService: Image too large', [
                    'url' => $url,
                    'size' => $size,
                ]);
                return null;
            }

            $contentType = $response->header('Content-Type');
            if (!$this->isSupportedImageType($contentType)) {
                Log::warning('ImageEmbeddingService: Unsupported image type', [
                    'url' => $url,
                    'content_type' => $contentType,
                ]);
                return null;
            }

            return base64_encode($imageData);
        } catch (\Exception $e) {
            Log::error('ImageEmbeddingService: Error downloading image', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Load image from local storage.
     *
     * @param  string  $path
     * @return string|null  Base64 encoded image data
     */
    private function loadImageFromStorage(string $path): ?string
    {
        try {
            foreach (['public', 'local'] as $diskName) {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    return base64_encode($disk->get($path));
                }
            }

            Log::warning('ImageEmbeddingService: File not found', ['path' => $path]);

            return null;
        } catch (\Exception $e) {
            Log::error('ImageEmbeddingService: Error reading file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Call Hugging Face API to get embedding.
     *
     * @param  string  $base64Image  Base64 encoded image
     * @return array<float>|null  Embedding vector
     */
    private function callHuggingFaceAPI(string $base64Image): ?array
    {
        $token = config('catalog-vision.huggingface_token')
            ?? \App\Models\BotSetting::first()?->huggingface_token;

        if (! $token) {
            Log::warning('ImageEmbeddingService: No Hugging Face token configured');

            return null;
        }

        $model = config('catalog-vision.clip_model');
        $apiUrl = rtrim((string) config('catalog-vision.huggingface_api_url'), '/');
        $binary = base64_decode($base64Image, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $endpoints = [
            ['url' => "{$apiUrl}/pipeline/feature-extraction/{$model}", 'mode' => 'binary'],
            ['url' => "{$apiUrl}/models/{$model}", 'mode' => 'json'],
        ];

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            foreach ($endpoints as $endpoint) {
                try {
                    $request = Http::withToken($token)->timeout(60);

                    $response = $endpoint['mode'] === 'binary'
                        ? $request->withBody($binary, 'image/jpeg')->post($endpoint['url'])
                        : $request->post($endpoint['url'], ['inputs' => $base64Image]);

                    if ($response->status() === 503 && $attempt < self::MAX_RETRIES) {
                        Log::info('ImageEmbeddingService: Model loading, retrying', [
                            'attempt' => $attempt,
                            'endpoint' => $endpoint['url'],
                        ]);
                        usleep(self::RETRY_DELAY_MS * $attempt * 1000);

                        continue 2;
                    }

                    if (! $response->successful()) {
                        Log::warning('ImageEmbeddingService: API error', [
                            'status' => $response->status(),
                            'endpoint' => $endpoint['url'],
                            'body' => mb_substr($response->body(), 0, 300),
                        ]);

                        continue;
                    }

                    $embedding = $this->parseEmbeddingResponse($response->json());
                    if ($embedding !== null) {
                        Log::info('ImageEmbeddingService: Successfully generated embedding', [
                            'model' => $model,
                            'dimension' => count($embedding),
                            'endpoint' => $endpoint['url'],
                        ]);

                        return $embedding;
                    }
                } catch (\Exception $e) {
                    Log::error('ImageEmbeddingService: Request error', [
                        'attempt' => $attempt,
                        'endpoint' => $endpoint['url'],
                        'error' => $e->getMessage(),
                    ]);
                }
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
    private function parseEmbeddingResponse(mixed $data): ?array
    {
        $flat = $this->flattenEmbedding($data);
        if ($flat === null || $flat === []) {
            return null;
        }

        $dimension = count($flat);
        if ($dimension < 128 || $dimension > 2048) {
            Log::warning('ImageEmbeddingService: Unexpected embedding dimension', [
                'dimension' => $dimension,
            ]);

            return null;
        }

        return $flat;
    }

    /**
     * @param  mixed  $data
     * @return array<float>|null
     */
    private function flattenEmbedding(mixed $data): ?array
    {
        if (! is_array($data) || $data === []) {
            return null;
        }

        if (is_numeric($data[0] ?? null)) {
            return array_map('floatval', $data);
        }

        if (is_array($data[0] ?? null)) {
            return $this->flattenEmbedding($data[0]);
        }

        return null;
    }

    /**
     * Check if content type is a supported image format.
     *
     * @param  string|null  $contentType
     * @return bool
     */
    private function isSupportedImageType(?string $contentType): bool
    {
        if (!$contentType) {
            return true; // Assume it's an image if no content-type
        }

        $contentType = strtolower(explode(';', $contentType)[0]);
        if (str_starts_with($contentType, 'image/')) {
            return true;
        }

        return in_array($contentType, ['application/octet-stream', 'binary/octet-stream'], true);
    }
}
