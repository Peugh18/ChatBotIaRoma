<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ServicioValidacionPrecios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicioValidacionPreciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_stock_returns_error_when_color_not_found()
    {
        $validation = ServicioValidacionPrecios::validateStock(999, 'rojo', 'M', 1);

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

        $validation = ServicioValidacionPrecios::validateStock($product->id, 'rojo', 'M', 1);

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

        $validation = ServicioValidacionPrecios::validateStock($product->id, 'rojo', 'M', 1);

        $this->assertTrue($validation['available']);
        $this->assertEquals(3, $validation['stock']);
        $this->assertNull($validation['error']);
    }
}
