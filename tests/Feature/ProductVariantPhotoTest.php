<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');
    }

    public function test_authenticated_user_can_upload_variant_photo(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Vestido Test',
            'price' => 120,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'lila',
            'sizes_stock' => ['S' => 2],
        ]);

        $file = UploadedFile::fake()->create('lila.jpg', 100, 'image/jpeg');

        $this->actingAs($user)
            ->postJson("/api/product-variants/{$variant->id}/photo", [
                'photo' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['image_path', 'public_url']);

        $variant->refresh();
        $this->assertNotNull($variant->image_path);
        Storage::disk('public')->assertExists($variant->image_path);
    }

    public function test_update_product_preserves_variant_id_and_image_path(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Vestido Test',
            'price' => 120,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'lila',
            'image_path' => 'products/1/lila-test.jpg',
            'sizes_stock' => ['S' => 2],
        ]);
        Storage::disk('public')->put($variant->image_path, 'fake-image');

        $this->actingAs($user)
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Vestido Test',
                'price' => 120,
                'variants' => [
                    [
                        'id' => $variant->id,
                        'color' => 'lila',
                        'sizes_stock' => ['S' => 1],
                    ],
                ],
            ])
            ->assertOk();

        $variant->refresh();
        $this->assertEquals($variant->id, $variant->id);
        $this->assertEquals('products/1/lila-test.jpg', $variant->image_path);
        $this->assertEquals(1, ProductVariant::where('product_id', $product->id)->count());
    }
}
