<?php

namespace App\Ventas\Constructores;

use App\Models\Customer;
use App\Services\ServicioConfigNegocio;

class ConstructorMensaje
{
    public function __construct(
        protected ServicioConfigNegocio $config,
    ) {}

    public function plantilla(string $clave, array $vars = []): string
    {
        $texto = (string) config("copy_ventas.{$clave}", '');
        foreach ($vars as $k => $v) {
            $texto = str_replace('{'.$k.'}', (string) $v, $texto);
        }

        return trim($texto);
    }

    public function saludo(?Customer $cliente): string
    {
        $presentacionCustom = trim((string) ($this->config->bot()?->mensaje_presentacion ?? ''));
        $cta = $this->plantilla('saludo_cta_categorias');
        $nombre = trim((string) ($cliente?->name ?? ''));
        if ($nombre !== '') {
            return $this->plantilla('saludo_regresa', [
                'nombre' => $nombre,
            ]);
        }

        if ($presentacionCustom !== '') {
            $lower = mb_strtolower($presentacionCustom);
            if (str_contains($lower, 'tenemos estos') || str_contains($presentacionCustom, 'Toca una')) {
                return $presentacionCustom;
            }

            return $presentacionCustom."\n\n".$cta;
        }

        return $this->plantilla('saludo_intro')."\n\n".$cta;
    }

    public function precioProducto(\App\Models\Product $producto): float
    {
        return $producto->precioFinal();
    }

    public function lineaPrecioConDescuento(\App\Models\Product $producto): string
    {
        $final = $this->precioProducto($producto);
        if ((float) ($producto->discount ?? 0) > 0) {
            return $this->plantilla('descuento_suerte', [
                'producto' => $producto->name,
                'precio' => number_format($final, 0),
            ]);
        }

        return $this->plantilla('producto_linea_precio', [
            'nombre' => $producto->name,
            'precio' => number_format($final, 0),
        ]);
    }
}
