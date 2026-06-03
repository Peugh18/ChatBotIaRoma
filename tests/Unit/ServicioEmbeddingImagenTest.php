<?php

namespace Tests\Unit;

use App\Services\ServicioEmbeddingImagen;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServicioEmbeddingImagenTest extends TestCase
{
    private function voyageSuccessResponse(int $dimensions = 1024): array
    {
        return [
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => array_fill(0, $dimensions, 0.1),
                    'index' => 0,
                ],
            ],
            'model' => 'voyage-multimodal-3.5',
        ];
    }

    public function test_get_embedding_returns_embedding_on_success_with_url(): void
    {
        config(['catalog-vision.voyage_api_key' => 'voyage_test_key']);

        Http::fake([
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response($this->voyageSuccessResponse(), 200),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('https://example.com/image.jpg', 'query');

        $this->assertIsArray($embedding);
        $this->assertCount(1024, $embedding);
    }

    public function test_get_embedding_returns_null_on_no_token(): void
    {
        config(['catalog-vision.voyage_api_key' => null]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_returns_null_on_api_error(): void
    {
        config(['catalog-vision.voyage_api_key' => 'voyage_test_key']);

        Http::fake([
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response(
                ['detail' => 'Unauthorized'],
                401
            ),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_accepts_custom_dimension_vectors(): void
    {
        config(['catalog-vision.voyage_api_key' => 'voyage_test_key']);

        Http::fake([
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response($this->voyageSuccessResponse(512), 200),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertIsArray($embedding);
        $this->assertCount(512, $embedding);
    }

    public function test_get_embedding_returns_null_on_tiny_dimension(): void
    {
        config(['catalog-vision.voyage_api_key' => 'voyage_test_key']);

        Http::fake([
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response($this->voyageSuccessResponse(10), 200),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_returns_null_on_empty_response(): void
    {
        config(['catalog-vision.voyage_api_key' => 'voyage_test_key']);

        Http::fake([
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response(['data' => []], 200),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_loads_from_storage_path(): void
    {
        config(['catalog-vision.voyage_api_key' => 'voyage_test_key']);
        Storage::fake('public');
        Storage::disk('public')->put('products/test.jpg', 'fake-image-data');

        Http::fake([
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response($this->voyageSuccessResponse(), 200),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding('products/test.jpg', 'document');

        $this->assertIsArray($embedding);
        $this->assertCount(1024, $embedding);
    }

    public function test_get_embedding_downloads_meta_url_with_wa_token(): void
    {
        config([
            'catalog-vision.voyage_api_key' => 'voyage_test_key',
            'services.roma.wa_token' => 'wa_test_token',
        ]);

        Http::fake([
            'https://lookaside.fbsbx.com/*' => Http::response('meta-image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'https://api.voyageai.com/v1/multimodalembeddings' => Http::response($this->voyageSuccessResponse(), 200),
        ]);

        $service = new ServicioEmbeddingImagen();
        $embedding = $service->getEmbedding(
            'https://lookaside.fbsbx.com/whatsapp/media/test',
            'query'
        );

        $this->assertIsArray($embedding);
        $this->assertCount(1024, $embedding);
    }
}
