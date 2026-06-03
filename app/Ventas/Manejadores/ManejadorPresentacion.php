<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Models\Product;
use App\Services\ServicioMediaProducto;
use App\Ventas\Constructores\ConstructorInteractivos;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;

class ManejadorPresentacion
{
    public function __construct(
        protected ConstructorMensaje $mensajes,
        protected ConstructorInteractivos $interactivos,
        protected MaquinaEstadosVentas $maquina,
        protected RepositorioCatalogo $catalogo,
        protected ServicioMediaProducto $media,
    ) {}

    public function mostrarProducto(ConversationState $estado, Product $producto): RespuestaBot
    {
        $bloques = [$this->mensajes->plantilla('producto_intro')];
        $bloques[] = $this->mensajes->lineaPrecioConDescuento($producto);
        $bloques[] = $this->mensajes->plantilla('producto_stock_por_color');

        $filas = $this->catalogo->stockPorColor($producto);
        $coloresConStock = [];
        foreach ($filas as $fila) {
            if ($fila['agotado']) {
                $bloques[] = $this->mensajes->plantilla('producto_color_agotado', ['color' => $fila['color']]);
            } else {
                $bloques[] = $this->mensajes->plantilla('producto_color_linea', [
                    'color' => $fila['color'],
                    'tallas' => implode(', ', $fila['tallas']),
                ]);
                $coloresConStock[] = $fila;
            }
        }

        if ($coloresConStock === []) {
            return $this->mostrarSimilaresDe($estado, $producto);
        }

        $opciones = [];
        foreach ($coloresConStock as $fila) {
            $colorSlug = rawurlencode($fila['color']);
            $opciones[] = [
                'id' => 'pick_color_'.$producto->id.'_'.$colorSlug,
                'title' => mb_substr($fila['color'], 0, 24),
            ];
        }
        $opciones[] = ['id' => 'ver_otros_modelos', 'title' => 'Ver otros modelos'];
        $opciones[] = ['id' => 'other_categories', 'title' => 'Otras categorías'];

        $cuerpo = implode("\n", $bloques)."\n\n".$this->mensajes->plantilla('producto_elige_color');
        $payload = $this->interactivos->construir($cuerpo, $opciones);
        $this->maquina->establecer($estado, EtapaVentas::PRODUCTO);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function elegirColor(ConversationState $estado, Product $producto, string $color): RespuestaBot
    {
        $variante = $this->catalogo->variantePorColor($producto, $color);
        if (! $variante || ! $this->catalogo->varianteTieneStock($variante)) {
            return $this->mostrarSimilaresDe($estado, $producto);
        }

        $ctx = $estado->context ?? [];
        $ctx['color_actual'] = $variante->color;
        $estado->context = $ctx;
        $estado->save();

        $url = $this->media->resolvePublicUrl($variante);
        $stock = $this->catalogo->stockTallasDeColor($producto, $variante->color);
        $opciones = [];
        $tallasOpciones = [];
        foreach ($stock as $talla => $qty) {
            if ($qty > 0) {
                $idx = count($tallasOpciones);
                $tallasOpciones[] = (string) $talla;
                $opciones[] = [
                    'id' => 'size_idx_'.$idx,
                    'title' => (string) $talla,
                ];
            }
        }

        if ($opciones === []) {
            return $this->mostrarSimilaresPorTalla($estado, $producto, $variante->color);
        }

        $ctx['tallas_opciones'] = $tallasOpciones;
        $estado->context = $ctx;
        $estado->save();

        $payload = $this->interactivos->construir(
            $this->mensajes->plantilla('pregunta_talla'),
            $opciones
        );
        $this->maquina->establecer($estado, EtapaVentas::TALLA);

        $resp = RespuestaBot::conInteractivo(
            $this->mensajes->plantilla('color_confirmado', ['color' => $variante->color]),
            $payload
        );

        if ($url) {
            $resp->conImagen(
                $url,
                $this->mensajes->plantilla('foto_color_caption', [
                    'producto' => $producto->name,
                    'color' => $variante->color,
                ])
            );
        }

        return $resp;
    }

    public function elegirTalla(ConversationState $estado, Product $producto, string $talla): RespuestaBot
    {
        $ctx = $estado->context ?? [];
        $color = (string) ($ctx['color_actual'] ?? '');
        $stock = $this->catalogo->stockTallasDeColor($producto, $color);
        $tallaNorm = mb_strtoupper(trim($talla));
        $qty = 0;
        foreach ($stock as $t => $q) {
            if (mb_strtoupper((string) $t) === $tallaNorm) {
                $qty = (int) $q;
                break;
            }
        }

        if ($qty < 1) {
            return $this->mostrarSimilaresPorTalla($estado, $producto, $color, $tallaNorm);
        }

        $ctx['talla_actual'] = $tallaNorm;
        $estado->context = $ctx;
        $estado->save();

        $linea = [
            'producto_id' => $producto->id,
            'nombre' => $producto->name,
            'color' => $color,
            'talla' => $tallaNorm,
            'precio' => $this->mensajes->precioProducto($producto),
        ];

        $carrito = $this->maquina->carrito($estado);
        $carrito[] = $linea;
        $max = (int) config('flujo_ventas.max_lineas_carrito', 10);
        if (count($carrito) > $max) {
            $carrito = array_slice($carrito, -$max);
        }
        $this->maquina->guardarCarrito($estado, $carrito);

        $texto = $this->mensajes->plantilla('talla_confirmada', ['talla' => $tallaNorm])."\n\n".
            $this->mensajes->plantilla('linea_carrito', [
                'producto' => $producto->name,
                'color' => $color,
                'talla' => $tallaNorm,
                'precio' => number_format($linea['precio'], 0),
            ])."\n\n".
            $this->mensajes->plantilla('pregunta_mas_o_confirmar');

        $payload = $this->interactivos->construir($texto, [
            ['id' => 'add_more_product', 'title' => 'Agregar otro'],
            ['id' => 'confirm_cart', 'title' => 'Confirmar pedido'],
        ]);

        $this->maquina->establecer($estado, EtapaVentas::MAS_O_CONFIRMAR);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function reMostrarMasOConfirmar(ConversationState $estado): RespuestaBot
    {
        $carrito = $this->maquina->carrito($estado);
        $ultima = $carrito !== [] ? $carrito[array_key_last($carrito)] : null;
        $texto = $ultima
            ? $this->mensajes->plantilla('linea_carrito', [
                'producto' => $ultima['nombre'] ?? '',
                'color' => $ultima['color'] ?? '',
                'talla' => $ultima['talla'] ?? '',
                'precio' => number_format((float) ($ultima['precio'] ?? 0), 0),
            ])."\n\n".$this->mensajes->plantilla('pregunta_mas_o_confirmar')
            : $this->mensajes->plantilla('pregunta_mas_o_confirmar');

        $payload = $this->interactivos->construir($texto, [
            ['id' => 'add_more_product', 'title' => 'Agregar otro'],
            ['id' => 'confirm_cart', 'title' => 'Confirmar pedido'],
        ]);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function masProductos(ConversationState $estado): RespuestaBot
    {
        $ctx = $estado->context ?? [];
        $catId = (int) ($ctx['categoria_actual_id'] ?? 0);
        if ($catId < 1) {
            return RespuestaBot::texto($this->mensajes->plantilla('agregar_otro_intro'));
        }

        return app(ManejadorNavegacion::class)->listarProductos($estado, $catId, 0);
    }

    public function mostrarSimilaresDe(ConversationState $estado, Product $producto): RespuestaBot
    {
        $similares = $this->catalogo->similaresDe($producto, 3);
        if ($similares->isEmpty()) {
            return RespuestaBot::texto($this->mensajes->plantilla('producto_sin_colores'));
        }

        $cuerpo = $this->mensajes->plantilla('similares_intro');
        $opciones = $this->catalogo->opcionesInteractivasDeProductos($similares, 'pick_similar');
        $opciones[] = ['id' => 'other_categories', 'title' => 'Otras categorías'];

        $payload = $this->interactivos->construir($cuerpo, $opciones);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function mostrarSimilaresGenerico(ConversationState $estado, int $productoIdExcluido): RespuestaBot
    {
        $ref = Product::with('variants')->find($productoIdExcluido);

        return $ref
            ? $this->mostrarSimilaresDe($estado, $ref)
            : RespuestaBot::texto($this->mensajes->plantilla('sin_datos_bd'));
    }

    public function mostrarSimilaresPorTalla(
        ConversationState $estado,
        Product $producto,
        string $color,
        ?string $talla = null
    ): RespuestaBot {
        $tallaBuscar = $talla ?? '';
        $similares = $tallaBuscar !== ''
            ? $this->catalogo->similaresConTalla($producto, $tallaBuscar, 3)
            : $this->catalogo->similaresDe($producto, 3);

        if ($similares->isEmpty()) {
            return RespuestaBot::texto($this->mensajes->plantilla('talla_sin_stock', ['color' => $color]));
        }

        $cuerpo = $this->mensajes->plantilla('talla_sin_stock', ['color' => $color]);
        $opciones = $this->catalogo->opcionesInteractivasDeProductos($similares, 'pick_similar');
        $payload = $this->interactivos->construir($cuerpo, $opciones);

        return RespuestaBot::conInteractivo('', $payload);
    }
}
