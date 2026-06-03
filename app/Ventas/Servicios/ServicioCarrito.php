<?php

namespace App\Ventas\Servicios;

use App\Models\Product;
use App\Ventas\Repositorios\RepositorioCatalogo;

class ServicioCarrito
{
    public function __construct(
        protected RepositorioCatalogo $catalogo,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{lineas: list<array<string, mixed>>, cambio: bool, subtotal: float}
     */
    public function revalidar(array $lineas): array
    {
        $nuevas = [];
        $cambio = false;

        foreach ($lineas as $linea) {
            $productoId = (int) ($linea['producto_id'] ?? $linea['product_id'] ?? 0);
            $producto = $this->catalogo->productoVendible($productoId);
            if (! $producto) {
                $cambio = true;

                continue;
            }

            $color = (string) ($linea['color'] ?? '');
            $talla = mb_strtoupper(trim((string) ($linea['talla'] ?? '')));
            $stock = $this->catalogo->stockTallasDeColor($producto, $color);
            $qty = 0;
            foreach ($stock as $t => $q) {
                if (mb_strtoupper((string) $t) === $talla) {
                    $qty = (int) $q;
                    break;
                }
            }

            if ($qty < 1) {
                $cambio = true;

                continue;
            }

            $precio = $producto->precioFinal();
            if ((float) ($linea['precio'] ?? 0) !== $precio) {
                $cambio = true;
            }

            $nuevas[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->name,
                'color' => $color,
                'talla' => $talla,
                'precio' => $precio,
            ];
        }

        return [
            'lineas' => $nuevas,
            'cambio' => $cambio || count($nuevas) !== count($lineas),
            'subtotal' => $this->subtotal($nuevas),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public function subtotal(array $lineas): float
    {
        $total = 0.0;
        foreach ($lineas as $l) {
            $total += (float) ($l['precio'] ?? 0);
        }

        return $total;
    }
}
