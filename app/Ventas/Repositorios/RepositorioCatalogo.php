<?php

namespace App\Ventas\Repositorios;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductoSimilar;
use App\Models\ProductVariant;
use App\Services\ServicioMediaProducto;
use App\Support\NormalizadorStockTallas;
use Illuminate\Support\Collection;

class RepositorioCatalogo
{
    /**
     * @return Collection<int, Category>
     */
    public function categoriasConProductosVisibles(): Collection
    {
        return Category::query()
            ->whereHas('products', fn ($q) => $q->where('status', '!=', Product::ESTADO_OCULTO))
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $c) => $this->productosVisiblesDeCategoria($c->id)->isNotEmpty());
    }

    /**
     * @return Collection<int, Product>
     */
    public function productosVisiblesDeCategoria(int $categoriaId, int $pagina = 0, int $porPagina = 10): Collection
    {
        $query = Product::query()
            ->with('variants')
            ->where('category_id', $categoriaId)
            ->where('status', '!=', Product::ESTADO_OCULTO)
            ->orderBy('name');

        return $query->get()->filter(fn (Product $p) => $this->productoTieneStockVendible($p))->values();
    }

    public function productoVendible(int $productoId): ?Product
    {
        $producto = Product::with('variants')->find($productoId);
        if (! $producto || ! $producto->esVisibleEnBot()) {
            return null;
        }
        if ($producto->status === Product::ESTADO_AGOTADO) {
            return null;
        }
        if (! $this->productoTieneStockVendible($producto)) {
            return null;
        }

        return $producto;
    }

    public function productoTieneStockVendible(Product $producto): bool
    {
        if ($producto->status === Product::ESTADO_AGOTADO) {
            return false;
        }

        foreach ($producto->variants as $variante) {
            if ($this->varianteTieneStock($variante)) {
                return true;
            }
        }

        return false;
    }

    public function varianteTieneStock(ProductVariant $variante): bool
    {
        $stock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);
        foreach ($stock as $qty) {
            if ($qty > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stock por color para mostrar al cliente (solo tallas con qty > 0).
     *
     * @return array<int, array{color: string, tallas: list<string>, agotado: bool}>
     */
    public function stockPorColor(Product $producto): array
    {
        $filas = [];
        foreach ($producto->variants as $variante) {
            $stock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);
            $tallas = [];
            foreach ($stock as $talla => $qty) {
                if ($qty > 0) {
                    $tallas[] = $talla;
                }
            }
            $filas[] = [
                'color' => $variante->color,
                'variante_id' => $variante->id,
                'tallas' => $tallas,
                'agotado' => $tallas === [],
            ];
        }

        return $filas;
    }

    /**
     * @return array<string, int>
     */
    public function stockTallasDeColor(Product $producto, string $color): array
    {
        $colorNorm = mb_strtolower(trim($color));
        foreach ($producto->variants as $variante) {
            if (mb_strtolower($variante->color) === $colorNorm) {
                return NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);
            }
        }

        return [];
    }

    public function variantePorColor(Product $producto, string $color): ?ProductVariant
    {
        $colorNorm = mb_strtolower(trim($color));
        foreach ($producto->variants as $variante) {
            if (mb_strtolower($variante->color) === $colorNorm) {
                return $variante;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Product>
     */
    public function similaresDe(Product $producto, int $limite = 3): Collection
    {
        $ids = ProductoSimilar::query()
            ->where('product_id', $producto->id)
            ->orderBy('orden')
            ->pluck('similar_product_id');

        $coleccion = collect();
        foreach ($ids as $id) {
            $p = $this->productoVendible((int) $id);
            if ($p) {
                $coleccion->push($p);
            }
        }

        if ($coleccion->count() >= $limite) {
            return $coleccion->take($limite);
        }

        $faltan = $limite - $coleccion->count();
        $auto = Product::query()
            ->with('variants')
            ->where('category_id', $producto->category_id)
            ->where('id', '!=', $producto->id)
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->whereNotIn('id', $coleccion->pluck('id'))
            ->limit($faltan * 3)
            ->get()
            ->filter(fn (Product $p) => $this->productoTieneStockVendible($p))
            ->take($faltan);

        return $coleccion->merge($auto)->take($limite);
    }

    /**
     * Productos similares que tengan una talla con stock (para oferta tras talla agotada).
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function similaresConTalla(Product $producto, string $talla, int $limite = 3): \Illuminate\Support\Collection
    {
        $tallaNorm = mb_strtoupper(trim($talla));
        $candidatos = $this->similaresDe($producto, $limite * 4);

        return $candidatos->filter(function (Product $p) use ($tallaNorm) {
            foreach ($p->variants as $variante) {
                $stock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);
                foreach ($stock as $t => $qty) {
                    if (mb_strtoupper((string) $t) === $tallaNorm && $qty > 0) {
                        return true;
                    }
                }
            }

            return false;
        })->take($limite)->values();
    }

    /**
     * @return list<array{id: string, title: string, description?: string}>
     */
    public function urlImagenPortada(Product $producto): ?string
    {
        $media = app(ServicioMediaProducto::class);
        foreach ($producto->variants as $variante) {
            if (! $this->varianteTieneStock($variante)) {
                continue;
            }
            $url = $media->resolvePublicUrl($variante);
            if ($url !== null && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    public function opcionesInteractivasDeProductos(\Illuminate\Support\Collection $productos, string $prefijoId = 'pick_similar'): array
    {
        $opciones = [];
        foreach ($productos as $p) {
            $precio = number_format($p->precioFinal(), 0);
            $opciones[] = [
                'id' => $prefijoId.'_'.$p->id,
                'title' => mb_substr($p->name, 0, 24),
                'description' => 'S/'.$precio,
            ];
        }

        return $opciones;
    }
}
