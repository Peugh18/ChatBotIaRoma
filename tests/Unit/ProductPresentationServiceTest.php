<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProductPresentationService;
use App\Services\SalesNudgeService;
use App\Services\ToolExecutorService;
use App\Services\BusinessConfigService;
use App\Services\ProductMediaService;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ProductPresentationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_with_only_size_s_shows_only_size_s_button()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 5, 'M' => 0, 'L' => 0],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        // Mock de ToolExecutorService
        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->andReturn(['success' => true]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => ['S' => 5, 'M' => 0, 'L' => 0],
            ]);

        $capturedButtons = null;
        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->once()
            ->with(
                Mockery::type(ConversationState::class),
                Mockery::type('string'),
                Mockery::capture($capturedButtons),
                'Elige talla'
            );

        // Mock de BusinessConfigService
        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        // Mock de ProductMediaService
        $productMediaMock = Mockery::mock(ProductMediaService::class);
        $productMediaMock->shouldReceive('colorsForProduct')
            ->andReturn([['color' => 'Rojo', 'has_stock' => true]]);

        // Crear el servicio con los mocks
        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'pick_color_' . $product->id . '_rojo');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('text', $result);

        // Verificar que solo hay un botón para talla S
        $this->assertNotNull($capturedButtons);
        $this->assertCount(1, $capturedButtons);
        $this->assertEquals('size_s', $capturedButtons[0]['id']);
        $this->assertEquals('Talla S', $capturedButtons[0]['title']);
    }

    public function test_product_with_s_m_l_in_stock_shows_three_buttons()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 5, 'M' => 3, 'L' => 2],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->andReturn(['success' => true]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => ['S' => 5, 'M' => 3, 'L' => 2],
            ]);

        $capturedButtons = null;
        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->once()
            ->with(
                Mockery::type(ConversationState::class),
                Mockery::type('string'),
                Mockery::capture($capturedButtons),
                'Elige talla'
            );

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        $productMediaMock = Mockery::mock(ProductMediaService::class);
        $productMediaMock->shouldReceive('colorsForProduct')
            ->andReturn([['color' => 'Rojo', 'has_stock' => true]]);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'pick_color_' . $product->id . '_rojo');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('text', $result);

        // Verificar que hay 3 botones
        $this->assertNotNull($capturedButtons);
        $this->assertCount(3, $capturedButtons);
        $this->assertEquals('size_s', $capturedButtons[0]['id']);
        $this->assertEquals('size_m', $capturedButtons[1]['id']);
        $this->assertEquals('size_l', $capturedButtons[2]['id']);
    }

    public function test_selecting_size_without_stock_returns_error_and_does_not_advance()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 5, 'M' => 0, 'L' => 0],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'current_color' => 'Rojo',
                'sales_stage' => 'awaiting_size_selection',
                'available_sizes' => ['S'],
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => ['S' => 5, 'M' => 0, 'L' => 0],
            ]);

        $capturedButtons = null;
        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->once()
            ->with(
                Mockery::type(ConversationState::class),
                Mockery::on(function ($text) {
                    return str_contains($text, 'no está disponible') || str_contains($text, 'no tiene stock');
                }),
                Mockery::capture($capturedButtons),
                'Elige talla'
            );

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock);

        $result = $service->handleSizeSelection($state, 'Quiero talla M');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('text', $result);
        $this->assertStringContainsString('no está disponible', $result['text']);

        // Verificar que NO avanzó al siguiente estado
        $state->refresh();
        $this->assertEquals('awaiting_size_selection', $state->context['sales_stage']);

        // Verificar que los botones solo muestran tallas disponibles
        $this->assertNotNull($capturedButtons);
        $this->assertCount(1, $capturedButtons);
        $this->assertEquals('size_s', $capturedButtons[0]['id']);
    }

    public function test_more_than_three_sizes_uses_interactive_list()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['XS' => 2, 'S' => 5, 'M' => 3, 'L' => 2, 'XL' => 1],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->andReturn(['success' => true]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => ['XS' => 2, 'S' => 5, 'M' => 3, 'L' => 2, 'XL' => 1],
            ]);

        $capturedSections = null;
        $toolExecutorMock->shouldReceive('executeSendInteractiveList')
            ->once()
            ->with(
                Mockery::type(ConversationState::class),
                Mockery::type('string'),
                'Ver tallas',
                Mockery::capture($capturedSections),
                'Elige talla'
            );

        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->never();

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        $productMediaMock = Mockery::mock(ProductMediaService::class);
        $productMediaMock->shouldReceive('colorsForProduct')
            ->andReturn([['color' => 'Rojo', 'has_stock' => true]]);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'pick_color_' . $product->id . '_rojo');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('text', $result);

        // Verificar que se usó lista interactiva con 5 tallas
        $this->assertNotNull($capturedSections);
        $this->assertCount(1, $capturedSections);
        $this->assertCount(5, $capturedSections[0]['rows']);
    }

    public function test_color_with_no_stock_returns_error_message()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => [],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->andReturn(['success' => true]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => [],
            ]);

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        $productMediaMock = Mockery::mock(ProductMediaService::class);
        $productMediaMock->shouldReceive('colorsForProduct')
            ->andReturn([['color' => 'Rojo', 'has_stock' => true]]);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'pick_color_' . $product->id . '_rojo');

        $this->assertNotNull($result);
        $this->assertStringContainsString('no tiene stock disponible', $result['text']);
    }

    public function test_color_without_image_shows_text_only()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['S' => 5, 'M' => 3],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => "El color 'lila' no tiene foto cargada.",
                'color' => 'lila',
            ]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => ['S' => 5, 'M' => 3],
            ]);

        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->once();

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        $productMediaMock = Mockery::mock(ProductMediaService::class);
        $productMediaMock->shouldReceive('colorsForProduct')
            ->andReturn([['color' => 'Lila', 'has_stock' => true]]);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'pick_color_' . $product->id . '_lila');

        $this->assertNotNull($result);
        $this->assertStringContainsString('foto en camino / consulta con asesor', $result['text']);
        $this->assertStringNotContainsString('📸', $result['text']); // No debe tener emoji de foto
    }

    public function test_color_with_image_shows_text_with_photo_emoji()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Vestido Test',
            'description' => 'Vestido de prueba',
            'price' => 100.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 5, 'M' => 3],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->andReturn([
                'success' => true,
                'image_url' => 'https://example.com/image.jpg',
            ]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'stock_by_size' => ['S' => 5, 'M' => 3],
            ]);

        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->once();

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn($text) => $text);

        $productMediaMock = Mockery::mock(ProductMediaService::class);
        $productMediaMock->shouldReceive('colorsForProduct')
            ->andReturn([['color' => 'Rojo', 'has_stock' => true]]);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'pick_color_' . $product->id . '_rojo');

        $this->assertNotNull($result);
        $this->assertStringContainsString('📸', $result['text']); // Debe tener emoji de foto
        $this->assertStringContainsString('📏 Tallas con stock:', $result['text']);
        $this->assertStringNotContainsString('�', $result['text']);
        $this->assertStringNotContainsString('foto en camino', $result['text']);
    }

    public function test_muestrame_en_color_camel_selects_camel_variant_color()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);

        $product = Product::create([
            'name' => 'Mariela',
            'description' => 'Vestido de prueba',
            'price' => 120.00,
            'discount' => 0,
            'category_id' => $category->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Camel',
            'sizes_stock' => ['L' => 1],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_id' => $product->id,
                'current_product_name' => $product->name,
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $toolExecutorMock = Mockery::mock(ToolExecutorService::class);
        $toolExecutorMock->shouldReceive('executeSendProductImage')
            ->once()
            ->with(Mockery::type(ConversationState::class), $product->id, 'Camel', Mockery::type('string'))
            ->andReturn(['success' => true]);

        $toolExecutorMock->shouldReceive('executeCheckStock')
            ->once()
            ->andReturn([
                'color' => 'Camel',
                'stock_by_size' => ['L' => 1],
            ]);

        $toolExecutorMock->shouldReceive('executeSendInteractiveButtons')
            ->once();

        $businessConfigMock = Mockery::mock(BusinessConfigService::class);
        $businessConfigMock->shouldReceive('applyBrandCta')
            ->andReturnUsing(fn ($text) => $text);

        $productMediaMock = Mockery::mock(ProductMediaService::class);

        $service = $this->makePresentationService($toolExecutorMock, $businessConfigMock, $productMediaMock);

        $result = $service->handleColorSelection($state, 'Muéstrame en color camel');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Color Camel', $result['text']);
        $this->assertEquals('Camel', $state->fresh()->context['current_color']);
    }

    private function makePresentationService(
        $toolExecutor,
        $business,
        $media = null,
        $salesNudge = null
    ): ProductPresentationService {
        $salesNudgeMock = $salesNudge ?? Mockery::mock(SalesNudgeService::class);
        $salesNudgeMock->shouldReceive('orderConfirmationText')
            ->zeroOrMoreTimes()
            ->andReturn("Último paso hermosa\n\n¿Confirmamos tu pedido? 💕");

        return new ProductPresentationService(
            $toolExecutor,
            $business,
            $media ?? Mockery::mock(ProductMediaService::class),
            $salesNudgeMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}