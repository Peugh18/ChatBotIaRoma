<?php

namespace Tests\Feature;

use App\Jobs\IndexVariantEmbeddingJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CatalogVisionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_catalog_vision_stats(): void
    {
        $this->getJson('/api/catalog-vision/stats')->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_catalog_vision_stats(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Test',
            'price' => 100,
            'category_id' => $category->id,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['M' => 1],
            'image_path' => 'products/1/rojo.jpg',
            'embedding' => array_fill(0, 8, 0.1),
            'embedding_indexed_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/catalog-vision/stats')
            ->assertOk()
            ->assertJsonPath('total_variants_with_photo', 1)
            ->assertJsonPath('indexed_variants', 1)
            ->assertJsonPath('pending_variants', 0);
    }

    public function test_reindex_queues_jobs_for_pending_variants(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        $product = Product::create([
            'name' => 'Pendiente',
            'price' => 90,
            'category_id' => $category->id,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Azul',
            'sizes_stock' => ['S' => 2],
            'image_path' => 'products/2/azul.jpg',
        ]);

        $this->actingAs($user)
            ->postJson('/api/catalog-vision/reindex')
            ->assertOk()
            ->assertJsonPath('queued', 1);

        Queue::assertPushed(IndexVariantEmbeddingJob::class, fn ($job) => $job->variantId === $variant->id);
    }
}
