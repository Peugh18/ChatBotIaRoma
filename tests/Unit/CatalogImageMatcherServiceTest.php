<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CatalogImageMatcherService;
use App\Services\ImageEmbeddingService;
use App\Services\ProductPresentationService;
use App\Services\ToolExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class CatalogImageMatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EMBEDDING_DIM = 768;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'catalog-vision.enabled' => true,
            'catalog-vision.min_similarity' => 0.72,
            'catalog-vision.top_k' => 3,
        ]);
    }

    public function test_clip_single_match_goes_to_product_pick(): void
    {
        $product = $this->seedProductWithEmbedding('Aurora', 'Rojo', 0.95);

        $this->mockQueryEmbedding(0.96);
        $this->mockPresentationPick($product->id, 'matched single');

        $state = ConversationState::create([
            'phone_number' => '51911112222',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $result = $this->matcher()->matchFromImage($state, 'https://example.com/live.jpg');

        $this->assertTrue($result['matched']);
        $this->assertStringContainsString('matched single', $result['text']);
        $this->assertEquals('rojo', mb_strtolower((string) $state->fresh()->context['image_color_preference']));
    }

    public function test_clip_multiple_matches_sets_product_selection_stage(): void
    {
        $p1 = $this->seedProductWithEmbedding('Aurora', 'Rojo', 0.92);
        $p2 = $this->seedProductWithEmbedding('Mariela', 'Camel', 0.88);
        $p3 = $this->seedProductWithEmbedding('Luna', 'Azul', 0.85);

        $this->mockQueryEmbedding(0.9);

        $tools = Mockery::mock(ToolExecutorService::class);
        $tools->shouldReceive('enqueueProductImage')
            ->times(3)
            ->with(
                Mockery::type(ConversationState::class),
                Mockery::type('int'),
                null,
                Mockery::type('string')
            )
            ->andReturn(['success' => true]);
        $tools->shouldReceive('executeSendInteractiveButtons')
            ->once()
            ->with(
                Mockery::type(ConversationState::class),
                Mockery::on(fn (string $text) => str_contains($text, 'Aurora')
                    && str_contains($text, 'Mariela')
                    && str_contains($text, 'Luna')),
                Mockery::on(function (array $buttons) use ($p1, $p2, $p3) {
                    $ids = array_column($buttons, 'id');

                    return count($buttons) === 3
                        && in_array('pick_product_'.$p1->id, $ids, true)
                        && in_array('pick_product_'.$p2->id, $ids, true)
                        && in_array('pick_product_'.$p3->id, $ids, true);
                }),
                'Elige modelo'
            );
        $this->app->instance(ToolExecutorService::class, $tools);

        $state = ConversationState::create([
            'phone_number' => '51911113333',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $result = $this->matcher()->matchFromImage($state, 'https://example.com/live.jpg');

        $this->assertTrue($result['matched']);
        $this->assertEquals('awaiting_product_selection', $state->fresh()->context['sales_stage']);
        $this->assertCount(3, $state->fresh()->context['last_shown_products']);
    }

    public function test_clip_no_indexed_variants_falls_back_to_text_search(): void
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Solo Texto',
            'price' => 100,
            'category_id' => $category->id,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Negro',
            'sizes_stock' => ['M' => 1],
        ]);

        $this->mockQueryEmbedding(0.9);

        $tools = Mockery::mock(ToolExecutorService::class);
        $tools->shouldReceive('executeGetProducts')
            ->once()
            ->andReturn([
                'count' => 1,
                'products' => [
                    ['id' => $product->id, 'name' => $product->name, 'final_price' => 100],
                ],
            ]);
        $this->app->instance(ToolExecutorService::class, $tools);

        $presentation = Mockery::mock(ProductPresentationService::class);
        $presentation->shouldReceive('presentProductPick')
            ->once()
            ->with(Mockery::type(ConversationState::class), $product->id)
            ->andReturn(['text' => 'fallback pick', 'metadata' => []]);
        $this->app->instance(ProductPresentationService::class, $presentation);

        $state = ConversationState::create([
            'phone_number' => '51911114444',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $result = $this->matcher()->matchFromImage($state, 'https://example.com/live.jpg', 'vestido negro');

        $this->assertTrue($result['matched']);
        $this->assertStringContainsString('fallback pick', $result['text']);
    }

    public function test_clip_disabled_uses_text_search_path(): void
    {
        config(['catalog-vision.enabled' => false]);

        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Desactivado',
            'price' => 80,
            'category_id' => $category->id,
        ]);

        $embeddingMock = Mockery::mock(ImageEmbeddingService::class);
        $embeddingMock->shouldNotReceive('getEmbedding');
        $this->app->instance(ImageEmbeddingService::class, $embeddingMock);

        $tools = Mockery::mock(ToolExecutorService::class);
        $tools->shouldReceive('executeGetProducts')
            ->once()
            ->andReturn([
                'count' => 1,
                'products' => [
                    ['id' => $product->id, 'name' => $product->name, 'final_price' => 80],
                ],
            ]);
        $this->app->instance(ToolExecutorService::class, $tools);

        $presentation = Mockery::mock(ProductPresentationService::class);
        $presentation->shouldReceive('presentProductPick')
            ->once()
            ->andReturn(['text' => 'sin clip', 'metadata' => []]);
        $this->app->instance(ProductPresentationService::class, $presentation);

        $state = ConversationState::create([
            'phone_number' => '51911115555',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $result = $this->matcher()->matchFromImage($state, 'https://example.com/live.jpg', 'desactivado');

        $this->assertTrue($result['matched']);
        $this->assertStringContainsString('sin clip', $result['text']);
    }

    public function test_no_matches_returns_matched_false(): void
    {
        config(['catalog-vision.enabled' => false]);

        $embeddingMock = Mockery::mock(ImageEmbeddingService::class);
        $this->app->instance(ImageEmbeddingService::class, $embeddingMock);

        $tools = Mockery::mock(ToolExecutorService::class);
        $tools->shouldReceive('executeGetProducts')
            ->once()
            ->andReturn(['count' => 0, 'products' => []]);
        $this->app->instance(ToolExecutorService::class, $tools);

        $state = ConversationState::create([
            'phone_number' => '51911116666',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $result = $this->matcher()->matchFromImage($state, 'https://example.com/live.jpg', 'vestido inexistente xyz');

        $this->assertFalse($result['matched']);
        $this->assertSame('', $result['text']);
    }

    /**
     * @return Product
     */
    private function seedProductWithEmbedding(string $name, string $color, float $baseValue): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'vestido'],
            ['name' => 'Vestido']
        );

        $product = Product::create([
            'name' => $name,
            'price' => 120,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => $color,
            'sizes_stock' => ['M' => 2],
            'embedding' => $this->vectorWith($baseValue),
            'embedding_indexed_at' => now(),
            'embedding_model' => 'test',
        ]);

        return $product;
    }

    /**
     * @return array<int, float>
     */
    private function vectorWith(float $value): array
    {
        return array_fill(0, self::EMBEDDING_DIM, $value);
    }

    private function mockQueryEmbedding(float $value): void
    {
        $embeddingMock = Mockery::mock(ImageEmbeddingService::class);
        $embeddingMock->shouldReceive('getEmbedding')
            ->once()
            ->with('https://example.com/live.jpg')
            ->andReturn($this->vectorWith($value));
        $this->app->instance(ImageEmbeddingService::class, $embeddingMock);
    }

    private function mockPresentationPick(int $productId, string $responseText): void
    {
        $tools = Mockery::mock(ToolExecutorService::class);
        $this->app->instance(ToolExecutorService::class, $tools);

        $presentation = Mockery::mock(ProductPresentationService::class);
        $presentation->shouldReceive('presentProductPick')
            ->once()
            ->with(Mockery::type(ConversationState::class), $productId)
            ->andReturn(['text' => $responseText, 'metadata' => []]);
        $this->app->instance(ProductPresentationService::class, $presentation);
    }

    private function matcher(): CatalogImageMatcherService
    {
        return app(CatalogImageMatcherService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
