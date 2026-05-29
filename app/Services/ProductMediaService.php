<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gestión de fotos de producto almacenadas en disco (no URLs externas).
 */
class ProductMediaService
{
    public function resolvePublicUrl(?ProductVariant $variant): ?string
    {
        if (!$variant) {
            return null;
        }

        if (!empty($variant->image_path)) {
            return $this->variantMediaUrl($variant);
        }

        if (!empty($variant->image_url)) {
            if (str_starts_with($variant->image_url, 'http://') || str_starts_with($variant->image_url, 'https://')) {
                return $this->resolveWhatsappSendUrl($variant->image_url);
            }

            return $this->resolveWhatsappSendUrl(url('/' . ltrim($variant->image_url, '/')));
        }

        return null;
    }

    /**
     * URL estable para que Meta/WhatsApp descargue la imagen del variant.
     */
    public function variantMediaUrl(ProductVariant $variant): string
    {
        return rtrim((string) config('app.public_url'), '/') . '/whatsapp-media/variants/' . $variant->id;
    }

    /**
     * Reescribe localhost → PUBLIC_APP_URL. Meta no puede leer 127.0.0.1 ni localhost.
     */
    public function resolveWhatsappSendUrl(string $url): string
    {
        $publicBase = rtrim((string) config('app.public_url'), '/');
        $appBase = rtrim((string) config('app.url'), '/');

        $bases = array_unique(array_filter([
            $appBase,
            'http://localhost',
            'https://localhost',
            'http://localhost:8000',
            'https://localhost:8000',
            'http://127.0.0.1',
            'http://127.0.0.1:8000',
        ]));

        foreach ($bases as $base) {
            if ($base !== '' && str_starts_with($url, $base)) {
                return $publicBase . substr($url, strlen($base));
            }
        }

        return $url;
    }

    public function isUrlReachableByMeta(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'];
        if (in_array(strtolower($host), $blocked, true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublic = (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            return $isPublic && str_starts_with($url, 'https://');
        }

        return str_starts_with($url, 'https://');
    }

    public function storeVariantPhoto(ProductVariant $variant, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $colorSlug = Str::slug($variant->color) ?: 'color';
        $path = "products/{$variant->product_id}/{$colorSlug}-" . uniqid() . ".{$extension}";

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if ($variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
        }

        $variant->update([
            'image_path' => $path,
            'image_url' => null,
        ]);

        return $path;
    }

    /**
     * @return array<int, array{color: string, has_stock: bool, image_url: string|null}>
     */
    public function colorsForProduct(Product $product): array
    {
        $product->loadMissing('variants');
        $colors = [];

        foreach ($product->variants as $variant) {
            $stock = $variant->sizes_stock ?? [];
            $hasStock = is_array($stock) && array_sum($stock) > 0;
            $colors[] = [
                'color' => $variant->color,
                'has_stock' => $hasStock,
                'image_url' => $this->resolvePublicUrl($variant),
            ];
        }

        return $colors;
    }
}
