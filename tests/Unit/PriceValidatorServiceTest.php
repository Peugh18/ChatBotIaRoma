<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PriceValidatorService;
use App\Services\ToolExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceValidatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_stock_returns_error_when_color_not_found()
    {
        $validation = PriceValidatorService::validateStock(999, 'rojo', 'M', 1);

        $this->assertFalse($validation['available']);
        $this->assertEquals(0, $validation['stock']);
        $this->assertStringContainsString('Color', $validation['error']);
    }

    public function test_validate_stock_returns_error_when_insufficient_stock()
    {
        // Crear producto con stock limitado
        $product = Product::create([
            'name' => 'Vestido Test',
            'price' => 100,
            'description' => 'Test',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'rojo',
            'sizes_stock' => [
                'S' => 5,
                'M' => 0, // Sin stock en M
                'L' => 3,
            ],
        ]);

        $validation = PriceValidatorService::validateStock($product->id, 'rojo', 'M', 1);

        $this->assertFalse($validation['available']);
        $this->assertEquals(0, $validation['stock']);
        $this->assertStringContainsString('insuficiente', $validation['error']);
    }

    public function test_validate_stock_returns_true_when_sufficient_stock()
    {
        $product = Product::create([
            'name' => 'Vestido Test',
            'price' => 100,
            'description' => 'Test',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'rojo',
            'sizes_stock' => [
                'S' => 5,
                'M' => 3,
                'L' => 2,
            ],
        ]);

        $validation = PriceValidatorService::validateStock($product->id, 'rojo', 'M', 1);

        $this->assertTrue($validation['available']);
        $this->assertEquals(3, $validation['stock']);
        $this->assertNull($validation['error']);
    }

    public function test_execute_create_order_fails_when_stock_is_zero()
    {
        $product = Product::create([
            'name' => 'Vestido Sin Stock',
            'price' => 100,
            'description' => 'Test',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'rojo',
            'sizes_stock' => [
                'M' => 0, // Sin stock
            ],
        ]);

        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => 'Maria Test',
            'email' => 'maria@test.com',
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'current_product_id' => $product->id,
                'current_color' => 'rojo',
                'current_size' => 'M',
                'order_confirmed' => true,
            ],
        ]);

        $toolExecutor = app(ToolExecutorService::class);

        $result = $toolExecutor->executeCreateOrder(
            $state,
            [
                [
                    'product_id' => $product->id,
                    'color' => 'rojo',
                    'size' => 'M',
                    'qty' => 1,
                ],
            ],
            'motorizado',
            'yape',
            null,
            null,
            true
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('insuficiente', $result['error']);
    }

    public function test_execute_create_order_succeeds_when_stock_is_available()
    {
        $product = Product::create([
            'name' => 'Vestido Con Stock',
            'price' => 100,
            'description' => 'Test',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'azul',
            'sizes_stock' => [
                'M' => 5, // Con stock
            ],
        ]);

        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => 'Maria Test',
            'email' => 'maria@test.com',
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'current_product_id' => $product->id,
                'current_color' => 'azul',
                'current_size' => 'M',
                'order_confirmed' => true,
            ],
        ]);

        $toolExecutor = app(ToolExecutorService::class);

        $result = $toolExecutor->executeCreateOrder(
            $state,
            [
                [
                    'product_id' => $product->id,
                    'color' => 'azul',
                    'size' => 'M',
                    'qty' => 1,
                ],
            ],
            'motorizado',
            'yape',
            null,
            null,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['order_id']);
    }
}