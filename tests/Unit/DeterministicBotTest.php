<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Models\Category;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DeterministicBotService;
use App\Services\ToolExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeterministicBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BotSetting::create([
            'auto_reply_enabled' => true,
            'system_prompt' => 'test',
        ]);
    }

    public function test_returns_empty_response_when_auto_reply_is_disabled(): void
    {
        BotSetting::query()->update(['auto_reply_enabled' => false]);

        $result = app(DeterministicBotService::class)->process('51977776666', 'hola');

        $this->assertSame('', $result['text']);
        $this->assertSame([], $result['metadata']);
    }

    public function test_skips_processing_when_conversation_requires_human(): void
    {
        ConversationState::create([
            'phone_number' => '51977776666',
            'current_state' => 'greeting',
            'requires_human' => true,
            'context' => [],
        ]);

        $result = app(DeterministicBotService::class)->process('51977776666', 'hola');

        $this->assertSame('', $result['text']);
    }

    public function test_color_photo_request_during_size_selection_sends_requested_color(): void
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Mariela',
            'price' => 120,
            'category_id' => $category->id,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'camel',
            'sizes_stock' => ['L' => 1],
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'lila',
            'sizes_stock' => ['S' => 1],
        ]);

        ConversationState::create([
            'phone_number' => '51988887777',
            'current_state' => 'greeting',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
                'current_product_id' => $product->id,
                'current_color' => 'camel',
                'available_sizes' => ['L'],
            ],
        ]);

        $this->bindToolExecutorMock(function ($mock) use ($product) {
            $mock->shouldReceive('executeSendProductImage')
                ->once()
                ->with(Mockery::type(ConversationState::class), $product->id, 'lila', Mockery::any())
                ->andReturn(['success' => true, 'image_url' => 'https://example.com/lila.jpg']);
            $mock->shouldReceive('executeCheckStock')
                ->once()
                ->andReturn(['stock_by_size' => ['S' => 1]]);
            $mock->shouldReceive('executeSendInteractiveButtons')->once();
        });

        $result = app(DeterministicBotService::class)->process('51988887777', 'Tienes foto en lila??');

        $this->assertStringContainsString('Color lila', $result['text']);
        $this->assertStringContainsString('Tallas con stock', $result['text']);
    }

    public function test_switching_color_by_name_during_size_selection_updates_flow(): void
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Mariela',
            'price' => 120,
            'category_id' => $category->id,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'camel',
            'sizes_stock' => ['L' => 1],
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'azul',
            'sizes_stock' => ['M' => 2],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51988887779',
            'current_state' => 'greeting',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
                'current_product_id' => $product->id,
                'current_color' => 'camel',
                'available_sizes' => ['L'],
            ],
        ]);

        $this->bindToolExecutorMock(function ($mock) use ($product) {
            $mock->shouldReceive('executeSendProductImage')
                ->once()
                ->with(Mockery::type(ConversationState::class), $product->id, 'azul', Mockery::any())
                ->andReturn(['success' => true]);
            $mock->shouldReceive('executeCheckStock')
                ->once()
                ->andReturn(['stock_by_size' => ['M' => 2]]);
            $mock->shouldReceive('executeSendInteractiveButtons')->once();
        });

        $result = app(DeterministicBotService::class)->process('51988887779', 'azul');

        $this->assertStringContainsString('Color azul', $result['text']);
        $this->assertEquals('azul', $state->fresh()->context['current_color']);
    }

    public function test_color_photo_request_does_not_trigger_size_selection_error(): void
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Mariela',
            'price' => 120,
            'category_id' => $category->id,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'lila',
            'sizes_stock' => ['S' => 1],
        ]);

        ConversationState::create([
            'phone_number' => '51988887778',
            'current_state' => 'greeting',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
                'current_product_id' => $product->id,
                'current_color' => 'lila',
                'available_sizes' => ['S'],
            ],
        ]);

        $this->bindToolExecutorMock(function ($mock) use ($product) {
            $mock->shouldReceive('executeSendProductImage')
                ->once()
                ->with(Mockery::type(ConversationState::class), $product->id, 'lila', Mockery::any())
                ->andReturn(['success' => true]);
        });

        $result = app(DeterministicBotService::class)->process('51988887778', 'Tienes foto en lila??');

        $this->assertStringNotContainsString('talla TIENES', $result['text']);
        $this->assertStringNotContainsString('no está disponible', $result['text']);
    }

    protected function bindToolExecutorMock(callable $configure): void
    {
        $mock = Mockery::mock(ToolExecutorService::class);
        $configure($mock);
        $mock->shouldIgnoreMissing();
        $this->app->instance(ToolExecutorService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
