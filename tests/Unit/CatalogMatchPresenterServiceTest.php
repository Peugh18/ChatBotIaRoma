<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CatalogMatchPresenterService;
use App\Services\ToolExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CatalogMatchPresenterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_presents_three_products_with_enqueued_images(): void
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $p1 = Product::create(['name' => 'Aurora', 'price' => 120, 'category_id' => $category->id]);
        $p2 = Product::create(['name' => 'Luna', 'price' => 99, 'category_id' => $category->id]);
        ProductVariant::create(['product_id' => $p1->id, 'color' => 'Rojo', 'sizes_stock' => ['M' => 1]]);
        ProductVariant::create(['product_id' => $p2->id, 'color' => 'Azul', 'sizes_stock' => ['S' => 1]]);

        $state = ConversationState::create([
            'phone_number' => '51922223333',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $tools = Mockery::mock(ToolExecutorService::class);
        $tools->shouldReceive('enqueueProductImage')->twice()->andReturn(['success' => true]);
        $tools->shouldReceive('executeSendInteractiveButtons')->once();

        $service = new CatalogMatchPresenterService(
            $tools,
            app(\App\Services\BusinessConfigService::class)
        );

        $result = $service->presentProductOptions($state, [
            ['id' => $p1->id, 'name' => 'Aurora', 'final_price' => 120],
            ['id' => $p2->id, 'name' => 'Luna', 'final_price' => 99],
        ]);

        $this->assertTrue($result['matched']);
        $this->assertStringContainsString('Aurora', $result['text']);
        $this->assertEquals('awaiting_product_selection', $state->fresh()->context['sales_stage']);
        $this->assertCount(2, $state->fresh()->context['last_shown_products']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
