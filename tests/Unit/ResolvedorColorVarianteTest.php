<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\NormalizadorStockTallas;
use App\Support\ResolvedorColorVariante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolvedorColorVarianteTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_color_from_phrase_with_product_name(): void
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Aurora',
            'price' => 122,
            'category_id' => $category->id,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['XXL' => 2],
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Naranja',
            'sizes_stock' => ['S' => 1],
        ]);

        $color = ResolvedorColorVariante::resolve($product->id, 'Muéstrame foto de aurora rojo');

        $this->assertSame('Rojo', $color);
    }

    public function test_size_resolver_matches_xxl_when_stock_key_is_lowercase(): void
    {
        $stock = NormalizadorStockTallas::normalize(['xxl' => 2, 'xl' => 0]);
        $size = NormalizadorStockTallas::resolveFromMessage('Talla XXL', $stock, ['XXL']);

        $this->assertSame('XXL', $size);
        $this->assertTrue((int) ($stock['XXL'] ?? 0) > 0);
    }

    public function test_size_resolver_matches_single_letter_m(): void
    {
        $stock = NormalizadorStockTallas::normalize(['M' => 3, 'L' => 0]);
        $size = NormalizadorStockTallas::resolveFromMessage('M', $stock, ['M']);

        $this->assertSame('M', $size);
    }
}
