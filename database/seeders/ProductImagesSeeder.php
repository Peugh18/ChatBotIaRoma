<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductImagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📦 Creating sample products with images...');

        // 1. Crear categorías
        $nightCategory = Category::firstOrCreate(
            ['name' => 'Noche'],
            ['slug' => 'noche', 'description' => 'Vestidos para eventos nocturnos']
        );

        $partyCategory = Category::firstOrCreate(
            ['name' => 'Fiesta'],
            ['slug' => 'fiesta', 'description' => 'Vestidos para fiestas y celebraciones']
        );

        $this->command->info('✅ Categories created/updated');

        // 2. Crear productos con imágenes usando URLs públicas
        $products = [
            [
                'name' => 'Vestido Elegante Borgoña',
                'description' => 'Vestido de noche elegante en color borgoña. Perfecto para eventos especiales.',
                'price' => 289.90,
                'stock' => 15,
                'category_id' => $nightCategory->id,
                'variants' => [
                    ['size' => 'S', 'color' => 'borbón', 'stock' => 3],
                    ['size' => 'M', 'color' => 'borbón', 'stock' => 5],
                    ['size' => 'L', 'color' => 'borbón', 'stock' => 7],
                ],
            ],
            [
                'name' => 'Vestido Velour Rosa',
                'description' => 'Vestido velour rosa para eventos de día o noche. Cuello redondo suave.',
                'price' => 319.90,
                'stock' => 10,
                'category_id' => $partyCategory->id,
                'variants' => [
                    ['size' => 'S', 'color' => 'rosa', 'stock' => 2],
                    ['size' => 'M', 'color' => 'rosa', 'stock' => 4],
                    ['size' => 'L', 'color' => 'rosa', 'stock' => 4],
                ],
            ],
            [
                'name' => 'Vestido Negro Formal',
                'description' => 'Vestido formal negro clásico. Incluye accesorios en oferta.',
                'price' => 349.90,
                'stock' => 8,
                'category_id' => $nightCategory->id,
                'variants' => [
                    ['size' => 'M', 'color' => 'negro', 'stock' => 3],
                    ['size' => 'L', 'color' => 'negro', 'stock' => 5],
                ],
            ],
        ];

        $productIds = [];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => str_slug($productData['name']),
                'description' => $productData['description'],
                'price' => $productData['price'],
                'stock' => $productData['stock'],
                'category_id' => $productData['category_id'],
                'featured' => true,
            ]);

            $productIds[$product->id] = $productData['variants'];

            foreach ($productData['variants'] as $variantData) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'size' => $variantData['size'],
                    'color' => $variantData['color'],
                    'price' => $productData['price'],
                    'stock' => $variantData['stock'],
                    'image_path' => 'https://via.placeholder.com/400x500/ff6b6b/ffffff?text='.$this->urlEncode($productData['name']),
                ]);
            }

            $this->command->info("✅ Product created: {$product->name}");
        }

        $this->command->info('✅ All products created successfully');

        // 3. Reportar resumen
        $this->command->info('📊 Summary:');
        $this->command->info('  - Products created: '.count($productIds));
        foreach ($productIds as $productId => $variants) {
            $product = Product::find($productId);
            $this->command->info("    - {$product->name} ({$product->category->name}): ".count($variants).' variants');
        }
    }

    private function urlEncode(string $text): string
    {
        return str_replace([' ', '%'], ['-', '%25'], $text);
    }
}
