<?php

namespace App\Ventas\Manejadores;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Services\ServicioConfigNegocio;
use App\Services\ServicioEscalamientoHumano;
use App\Ventas\Constructores\ConstructorInteractivos;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;
use App\Ventas\Servicios\ServicioCarrito;

class ManejadorRespuestasTransversales
{
    public function __construct(
        protected ConstructorMensaje $mensajes,
        protected ConstructorInteractivos $interactivos,
        protected MaquinaEstadosVentas $maquina,
        protected RepositorioCatalogo $catalogo,
        protected ServicioCarrito $carrito,
    ) {}

    /**
     * Comandos globales (cualquier etapa). null = no aplica.
     */
    public function intentarGlobal(
        ConversationState $estado,
        Customer $cliente,
        string $mensaje,
        ?callable $mostrarCategorias,
        ?callable $mostrarOtrasCategorias = null,
    ): ?RespuestaBot {
        $m = trim($mensaje);

        if ($this->esOtrasCategorias($m) && $mostrarOtrasCategorias !== null) {
            return $mostrarOtrasCategorias();
        }

        if ($this->quiereReiniciar($m)) {
            if ($this->maquina->carrito($estado) !== []) {
                $this->maquina->establecer($estado, EtapaVentas::CONFIRMAR_REINICIO);

                return $this->pedirConfirmarReinicio();
            }
            $this->reiniciar($estado);

            return $mostrarCategorias();
        }

        if ($m === 'si reiniciar' || $m === 'restart_yes') {
            $this->reiniciar($estado);

            return $mostrarCategorias();
        }

        if ($m === 'no reiniciar' || $m === 'restart_no') {
            $this->maquina->salirConfirmarReinicio($estado);

            return RespuestaBot::texto($this->mensajes->plantilla('reinicio_cancelado'));
        }

        if ($this->quiereCarrito($m) || $m === 'ver carrito') {
            return $this->verCarrito($estado);
        }

        if ($this->quiereCategorias($m) || $m === 'ver categorias' || $m === 'rescue_categories') {
            return $mostrarCategorias();
        }

        if ($m === 'Quiero hablar con un asesor humano' || $m === 'rescue_human') {
            app(ServicioEscalamientoHumano::class)->escalar($estado, 'Cliente pidió asesora');

            return RespuestaBot::texto('')->marcarEscalamientoHumano();
        }

        if ($this->quiereContinuar($m) || $m === 'continuar pedido' || $m === 'rescue_continue') {
            return $this->reanudar($estado, $cliente, $mostrarCategorias);
        }

        $etapa = $this->maquina->obtener($estado);
        $enCheckout = in_array($etapa, [
            EtapaVentas::ENVIO_METODO,
            EtapaVentas::ENVIO_DATOS,
            EtapaVentas::DATOS_REUTILIZAR,
            EtapaVentas::RESUMEN,
            EtapaVentas::PAGO,
            EtapaVentas::COMPROBANTE,
            EtapaVentas::TARJETA_DATOS,
            EtapaVentas::VALIDACION_PAGO,
        ], true);

        if (! $enCheckout && $this->esPreguntaFrecuente($m)) {
            return $this->responderFaq($m);
        }

        return null;
    }

    public function menuRescate(?Customer $cliente): RespuestaBot
    {
        $nombre = trim((string) ($cliente?->name ?? ''));
        $intro = $nombre !== ''
            ? $this->mensajes->plantilla('rescate_intro', ['nombre' => $nombre.', '])
            : $this->mensajes->plantilla('rescate_sin_nombre');

        $payload = $this->interactivos->construir($intro, [
            ['id' => 'rescue_categories', 'title' => 'Ver categorías'],
            ['id' => 'rescue_cart', 'title' => 'Ver carrito'],
            ['id' => 'rescue_continue', 'title' => 'Continuar pedido'],
            ['id' => 'rescue_human', 'title' => 'Hablar con asesora'],
        ]);

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function verCarrito(ConversationState $estado): RespuestaBot
    {
        $lineasPrevias = $this->maquina->carrito($estado);
        $revalidado = $this->carrito->revalidar($lineasPrevias);
        if ($revalidado['cambio']) {
            $this->maquina->guardarCarrito($estado, $revalidado['lineas']);
        }

        $lineas = $revalidado['lineas'];
        if ($lineas === []) {
            $teniaLineas = $lineasPrevias !== [];

            return RespuestaBot::texto(
                $teniaLineas && $revalidado['cambio']
                    ? $this->mensajes->plantilla('stock_carrito_cambio')
                    : $this->mensajes->plantilla('carrito_vacio')
            );
        }

        $textoLineas = [];
        foreach ($lineas as $l) {
            $textoLineas[] = $this->mensajes->plantilla('linea_carrito', [
                'producto' => $l['nombre'] ?? '',
                'color' => $l['color'] ?? '',
                'talla' => $l['talla'] ?? '',
                'precio' => number_format((float) ($l['precio'] ?? 0), 0),
            ]);
        }

        return RespuestaBot::texto($this->mensajes->plantilla('carrito_resumen', [
            'lineas' => implode("\n", $textoLineas),
            'subtotal' => number_format($revalidado['subtotal'], 0),
        ]));
    }

    public function pedirConfirmarReinicio(): RespuestaBot
    {
        $payload = $this->interactivos->construir(
            $this->mensajes->plantilla('confirmar_reinicio'),
            [
                ['id' => 'restart_yes', 'title' => 'Sí, empezar'],
                ['id' => 'restart_no', 'title' => 'No, seguir'],
            ]
        );

        return RespuestaBot::conInteractivo('', $payload);
    }

    public function reiniciar(ConversationState $estado): void
    {
        $this->maquina->reiniciarCarrito($estado);
    }

    /**
     * @param  callable(): RespuestaBot  $mostrarCategorias
     */
    public function reanudar(
        ConversationState $estado,
        Customer $cliente,
        callable $mostrarCategorias
    ): RespuestaBot {
        $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
        if ($revalidado['cambio']) {
            $this->maquina->guardarCarrito($estado, $revalidado['lineas']);
        }

        if ($revalidado['lineas'] === []) {
            return $mostrarCategorias();
        }

        $etapa = $this->maquina->obtener($estado);
        $resumen = $this->mensajes->plantilla('carrito_resumen', [
            'lineas' => implode("\n", array_map(fn ($l) => $this->mensajes->plantilla('linea_carrito', [
                'producto' => $l['nombre'],
                'color' => $l['color'],
                'talla' => $l['talla'],
                'precio' => number_format((float) $l['precio'], 0),
            ]), $revalidado['lineas'])),
            'subtotal' => number_format($revalidado['subtotal'], 0),
        ]);

        if ($revalidado['cambio']) {
            return RespuestaBot::texto(
                $this->mensajes->plantilla('stock_carrito_cambio')."\n\n".$resumen
            );
        }

        return RespuestaBot::texto($this->mensajes->plantilla('reanudar_pedido', [
            'resumen' => $resumen,
        ]));
    }

    public function esPreguntaFrecuente(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));

        return preg_match('/\b(horario|hora de entrega|cuando entregan|yape|número de yape|numero de yape|envío|envio|delivery)\b/u', $m) === 1;
    }

    public function responderFaq(string $mensaje): RespuestaBot
    {
        $m = mb_strtolower($mensaje);
        if (str_contains($m, 'yape')) {
            $config = app(ServicioConfigNegocio::class);

            return RespuestaBot::texto($config->yapePaymentMessage());
        }
        if (str_contains($m, 'horario') || str_contains($m, 'entrega')) {
            return RespuestaBot::texto((string) config('flujo_ventas.horario_entregas'));
        }
        if (str_contains($m, 'envío') || str_contains($m, 'envio')) {
            return RespuestaBot::texto(
                "Hacemos envío por motorizado en Lima o por Shalom a provincia 🚚\n".
                (string) config('flujo_ventas.horario_entregas')
            );
        }

        return RespuestaBot::texto($this->mensajes->plantilla('sin_datos_bd'));
    }

    public function quiereReiniciar(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));

        return preg_match('/\b(reiniciar|empezar de nuevo|borrar carrito|nuevo pedido)\b/u', $m) === 1;
    }

    public function quiereCarrito(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));

        return str_contains($m, 'carrito') || str_contains($m, 'ver carrito');
    }

    public function esOtrasCategorias(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        $sinTildes = str_replace(['í', 'á'], ['i', 'a'], $m);

        return in_array($sinTildes, ['otras categorias', 'other_categories'], true);
    }

    public function quiereCategorias(string $mensaje): bool
    {
        if ($this->esOtrasCategorias($mensaje)) {
            return false;
        }

        $m = mb_strtolower(trim($mensaje));

        return preg_match('/\b(categorias|categorías|ver categorias|catalogo|catálogo)\b/u', $m) === 1;
    }

    public function quiereContinuar(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));

        return str_contains($m, 'continuar pedido') || str_contains($m, 'seguir pedido');
    }
}
