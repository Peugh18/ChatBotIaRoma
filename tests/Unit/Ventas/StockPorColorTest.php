<?php

namespace Tests\Unit\Ventas;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Ventas\Repositorios\RepositorioCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPorColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_por_color_no_mezcla_tallas_entre_colores(): void
    {
        $producto = Product::create([
            'name' => 'Mariela Test',
            'price' => 199,
            'description' => 'Test',
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::create([
            'product_id' => $producto->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 2, 'M' => 1, 'L' => 0],
        ]);

        ProductVariant::create([
            'product_id' => $producto->id,
            'color' => 'Azul',
            'sizes_stock' => ['S' => 1, 'M' => 0],
        ]);

        $filas = app(RepositorioCatalogo::class)->stockPorColor($producto->load('variants'));

        $this->assertCount(2, $filas);
        $this->assertSame('Rojo', $filas[0]['color']);
        $this->assertSame(['S', 'M'], $filas[0]['tallas']);
        $this->assertSame('Azul', $filas[1]['color']);
        $this->assertSame(['S'], $filas[1]['tallas']);
    }
}
