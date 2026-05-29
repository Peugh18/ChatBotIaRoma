<?php

namespace Tests\Unit;

use App\Services\ImageEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageEmbeddingServiceTest extends TestCase
{
    public function test_get_embedding_returns_embedding_on_success()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-image-data', 200, ['Content-Type' => 'image/jpeg']),
            'https://api-inference.huggingface.co/*' => Http::response(
                array_fill(0, 768, 0.1),
                200
            ),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertIsArray($embedding);
        $this->assertCount(768, $embedding);
    }

    public function test_get_embedding_returns_null_on_no_token()
    {
        config(['catalog-vision.huggingface_token' => null]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_returns_null_on_404_error()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-image-data', 200, ['Content-Type' => 'image/jpeg']),
            'https://api-inference.huggingface.co/*' => Http::response(
                ['error' => 'Model not found'],
                404
            ),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_accepts_512_dimension_vectors()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-image-data', 200, ['Content-Type' => 'image/jpeg']),
            'https://api-inference.huggingface.co/*' => Http::response(
                array_fill(0, 512, 0.1),
                200
            ),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertIsArray($embedding);
        $this->assertCount(512, $embedding);
    }

    public function test_get_embedding_returns_null_on_tiny_dimension()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-image-data', 200, ['Content-Type' => 'image/jpeg']),
            'https://api-inference.huggingface.co/*' => Http::response(
                array_fill(0, 10, 0.1),
                200
            ),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_returns_null_on_empty_response()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-image-data', 200, ['Content-Type' => 'image/jpeg']),
            'https://api-inference.huggingface.co/*' => Http::response([], 200),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_returns_null_on_image_download_error()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/image.jpg' => Http::response('Not Found', 404),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/image.jpg');

        $this->assertNull($embedding);
    }

    public function test_get_embedding_returns_null_on_large_image()
    {
        config(['catalog-vision.huggingface_token' => 'hf_test_token_123']);

        Http::fake([
            'https://example.com/large.jpg' => Http::response(
                str_repeat('x', 11 * 1024 * 1024),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $service = new ImageEmbeddingService();
        $embedding = $service->getEmbedding('https://example.com/large.jpg');

        $this->assertNull($embedding);
    }
}
