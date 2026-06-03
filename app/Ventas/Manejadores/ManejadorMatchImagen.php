<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Ventas\Constructores\ConstructorInteractivos;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;
use App\Ventas\Servicios\ServicioMatchImagen;

class ManejadorMatchImagen
{
    public function __construct(
        protected ServicioMatchImagen $matcher,
        protected ConstructorMensaje $mensajes,
        protected ConstructorInteractivos $interactivos,
        protected MaquinaEstadosVentas $maquina,
        protected RepositorioCatalogo $catalogo,
        protected ManejadorPresentacion $presentacion,
    ) {}

    public function procesarFoto(ConversationState $estado, string $imageUrl): RespuestaBot
    {
        $resultado = $this->matcher->buscarPorImagen($imageUrl);

        if ($resultado['tipo'] === 'sin_config') {
            return RespuestaBot::texto($this->mensajes->plantilla('match_sin_voyage'));
        }

        if ($resultado['tipo'] === 'sin_token_wa') {
            return RespuestaBot::texto($this->mensajes->plantilla('match_sin_wa_token'));
        }

        $productos = $resultado['productos'];

        if ($resultado['tipo'] === 'alto' && $productos->count() === 1) {
            $producto = $this->catalogo->productoVendible($productos->first()->id);
            if ($producto) {
                $ctx = $estado->context ?? [];
                $ctx['producto_actual_id'] = $producto->id;
                $estado->context = $ctx;
                $estado->save();

                $texto = $this->mensajes->plantilla('match_uno', [
                    'producto' => $producto->name,
                    'precio' => number_format($this->mensajes->precioProducto($producto), 0),
                ]);

                return $this->fusionarMatchConFicha($texto, $this->presentacion->mostrarProducto($estado, $producto));
            }
        }

        if ($productos->isEmpty()) {
            return RespuestaBot::texto($this->mensajes->plantilla('match_sin_resultado'));
        }

        return $this->mostrarOpcionesMatch($estado, $productos, $resultado['tipo'] === 'medio');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Product>  $productos
     */
    public function mostrarOpcionesMatch(ConversationState $estado, $productos, bool $introMedio = true): RespuestaBot
    {
        $vendibles = $productos
            ->map(fn ($p) => $this->catalogo->productoVendible($p->id))
            ->filter()
            ->values();

        if ($vendibles->isEmpty()) {
            return RespuestaBot::texto($this->mensajes->plantilla('match_sin_resultado'));
        }

        $nombres = $vendibles->map(fn ($p) => $p->name)->take(4)->implode(', ');
        $cuerpo = $introMedio
            ? $this->mensajes->plantilla('match_intro', ['productos' => $nombres])
            : $this->mensajes->plantilla('match_sin_resultado');

        $opciones = $this->catalogo->opcionesInteractivasDeProductos($vendibles, 'pick_product');
        $opciones[] = ['id' => 'rescue_categories', 'title' => 'Ver categorías'];

        $payload = $this->interactivos->construir($cuerpo, $opciones);
        $this->maquina->establecer($estado, EtapaVentas::PRODUCTOS);

        return RespuestaBot::conInteractivo('', $payload);
    }

    protected function fusionarMatchConFicha(string $introMatch, RespuestaBot $ficha): RespuestaBot
    {
        $meta = $ficha->metadata;
        if (($meta['type'] ?? '') === 'interactive' && ! empty($meta['interactive']['body']['text'])) {
            $interactive = $meta['interactive'];
            $interactive['body']['text'] = trim($introMatch."\n\n".$interactive['body']['text']);

            return RespuestaBot::conInteractivo($introMatch, $interactive);
        }

        return $ficha->prefijarTexto($introMatch);
    }
}
