<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Ventas\Constructores\ConstructorInteractivos;
use App\Ventas\Constructores\PaginadorListasWhatsapp;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;

class ManejadorNavegacion
{
    public function __construct(
        protected ConstructorMensaje $mensajes,
        protected ConstructorInteractivos $interactivos,
        protected MaquinaEstadosVentas $maquina,
        protected RepositorioCatalogo $catalogo,
        protected ManejadorPresentacion $presentacion,
        protected ManejadorInicio $inicio,
        protected PaginadorListasWhatsapp $paginador,
    ) {}

    public function elegirCategoria(ConversationState $estado, int $categoriaId): RespuestaBot
    {
        $ctx = $estado->context ?? [];
        $ctx['categoria_actual_id'] = $categoriaId;
        $estado->context = $ctx;
        $estado->save();

        return $this->listarProductos($estado, $categoriaId, 0);
    }

    public function listarProductos(ConversationState $estado, int $categoriaId, int $pagina = 0): RespuestaBot
    {
        $productos = $this->catalogo->productosVisiblesDeCategoria($categoriaId);
        if ($productos->isEmpty()) {
            return RespuestaBot::texto($this->mensajes->plantilla('sin_datos_bd'));
        }

        $categoria = \App\Models\Category::find($categoriaId);

        $todas = [];
        foreach ($productos as $p) {
            $precio = number_format($this->mensajes->precioProducto($p), 0);
            $todas[] = [
                'id' => 'pick_product_'.$p->id,
                'title' => mb_substr($p->name, 0, 24),
                'description' => 'S/'.$precio,
            ];
        }

        $pag = $this->paginador->pagina($todas, $pagina, 'page_products');

        $maxFotos = (int) config('flujo_ventas.max_fotos_lista_productos', 4);
        if ($pagina === 0 && $maxFotos > 0) {
            $cola = [];
            foreach ($productos->take($maxFotos) as $p) {
                $url = $this->catalogo->urlImagenPortada($p);
                if ($url) {
                    $cola[] = [
                        'url' => $url,
                        'caption' => mb_substr($p->name, 0, 60),
                    ];
                }
            }
            if ($cola !== []) {
                $ctx = $estado->context ?? [];
                $ctx['pending_image_queue'] = $cola;
                $estado->context = $ctx;
                $estado->save();
            }
        }

        $nombreCat = $categoria?->name ?? 'esta categoría';
        $cuerpo = $pagina === 0
            ? $this->mensajes->plantilla('lista_productos_intro', ['categoria' => $nombreCat])
            : $this->mensajes->plantilla('lista_pagina_siguiente', ['pagina' => (string) ($pagina + 1)]);
        $payload = $this->interactivos->construir(
            $cuerpo,
            $pag['opciones'],
            $this->mensajes->plantilla('lista_productos_pie')
        );

        $this->maquina->establecer($estado, EtapaVentas::PRODUCTOS);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function elegirProducto(ConversationState $estado, int $productoId): RespuestaBot
    {
        $producto = $this->catalogo->productoVendible($productoId);
        if (! $producto) {
            return RespuestaBot::texto($this->mensajes->plantilla('sin_datos_bd'));
        }

        $ctx = $estado->context ?? [];
        $ctx['producto_actual_id'] = $productoId;
        unset($ctx['color_actual'], $ctx['talla_actual']);
        $estado->context = $ctx;
        $estado->save();

        return $this->presentacion->mostrarProducto($estado, $producto);
    }

    public function otrasCategorias(?\App\Models\Customer $cliente, ConversationState $estado): RespuestaBot
    {
        return $this->inicio->mostrarCategorias($cliente, $estado);
    }

    public function similaresDesdeProductoActual(ConversationState $estado): RespuestaBot
    {
        $productoId = (int) (($estado->context ?? [])['producto_actual_id'] ?? 0);
        $producto = $this->catalogo->productoVendible($productoId);

        return $producto
            ? $this->presentacion->mostrarSimilaresDe($estado, $producto)
            : RespuestaBot::texto($this->mensajes->plantilla('sin_datos_bd'));
    }
}
