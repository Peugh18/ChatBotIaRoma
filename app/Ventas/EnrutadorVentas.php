<?php

namespace App\Ventas;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Support\ResolvedorColorVariante;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\Contratos\RespuestaBot;
use App\Ventas\Manejadores\ManejadorCheckout;
use App\Ventas\Manejadores\ManejadorInicio;
use App\Ventas\Manejadores\ManejadorNavegacion;
use App\Ventas\Manejadores\ManejadorPresentacion;
use App\Ventas\Manejadores\ManejadorRespuestasTransversales;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Repositorios\RepositorioCatalogo;

/**
 * Enruta mensajes por etapa y acciones de catálogo (arquitectura del flujo V1).
 */
class EnrutadorVentas
{
    public function __construct(
        protected MaquinaEstadosVentas $maquina,
        protected ManejadorInicio $inicio,
        protected ManejadorNavegacion $navegacion,
        protected ManejadorPresentacion $presentacion,
        protected ManejadorCheckout $checkout,
        protected ManejadorRespuestasTransversales $transversal,
        protected RepositorioCatalogo $catalogo,
        protected ConstructorMensaje $mensajes,
    ) {}

    public function despachar(
        ConversationState $estado,
        Customer $cliente,
        string $mensaje,
        ?string $etapa
    ): RespuestaBot {
        $mostrarCategorias = fn () => $this->inicio->mostrarCategorias($cliente, $estado);
        $m = trim($mensaje);

        if ($global = $this->transversal->intentarGlobal($estado, $cliente, $mensaje, $mostrarCategorias)) {
            return $global;
        }

        if ($etapa === EtapaVentas::VALIDACION_PAGO) {
            if ($this->inicio->esSaludo($m) || $this->transversal->quiereReiniciar($m)) {
                $this->maquina->finalizarValidacionPago($estado);

                return $mostrarCategorias();
            }

            return $this->checkout->procesar($estado, $cliente, $mensaje, $etapa);
        }

        if ($this->esEtapaCheckout($etapa)) {
            return $this->checkout->procesar($estado, $cliente, $mensaje, $etapa);
        }

        if ($m === 'agregar otro producto' || $m === 'add_more_product') {
            return $this->presentacion->masProductos($estado);
        }

        if ($m === 'confirmar pedido' || $m === 'confirm_cart') {
            return $this->checkout->iniciarDesdeCarrito($estado, $cliente);
        }

        if ($m === 'otras categorias' || $m === 'other_categories') {
            return $this->navegacion->otrasCategorias($cliente, $estado);
        }

        if ($m === 'ver_otros_modelos') {
            return $this->navegacion->similaresDesdeProductoActual($estado);
        }

        if (preg_match('/^page_categories_(\d+)$/', $m, $match)) {
            return $this->inicio->mostrarCategorias($cliente, $estado, (int) $match[1]);
        }

        if (preg_match('/^pick_category_(\d+)$/', $m, $match)) {
            return $this->navegacion->elegirCategoria($estado, (int) $match[1]);
        }

        if (preg_match('/^page_products_(\d+)$/', $m, $match)) {
            $catId = (int) (($estado->context ?? [])['categoria_actual_id'] ?? 0);

            return $this->navegacion->listarProductos($estado, $catId, (int) $match[1]);
        }

        if (preg_match('/^pick_product_(\d+)$/', $m, $match)) {
            return $this->navegacion->elegirProducto($estado, (int) $match[1]);
        }

        if (preg_match('/^pick_similar_(\d+)$/', $m, $match)) {
            return $this->navegacion->elegirProducto($estado, (int) $match[1]);
        }

        if (preg_match('/^pick_color_(\d+)_(.+)$/', $m, $match)) {
            $producto = $this->catalogo->productoVendible((int) $match[1]);
            if (! $producto) {
                return $this->presentacion->mostrarSimilaresGenerico($estado, (int) $match[1]);
            }

            return $this->presentacion->elegirColor($estado, $producto, urldecode($match[2]));
        }

        if (preg_match('/^size_idx_(\d+)$/', $m, $match)) {
            return $this->elegirTallaPorIndice($estado, (int) $match[1]);
        }

        if (preg_match('/^Quiero talla (.+)$/iu', $m, $match)) {
            $productoId = (int) (($estado->context ?? [])['producto_actual_id'] ?? 0);
            $producto = $this->catalogo->productoVendible($productoId);
            if (! $producto) {
                return RespuestaBot::texto(config('copy_ventas.sin_datos_bd'));
            }

            return $this->presentacion->elegirTalla($estado, $producto, trim($match[1]));
        }

        if ($this->inicio->esSaludo($m) || $etapa === null || $etapa === EtapaVentas::INICIO) {
            return $mostrarCategorias();
        }

        if ($porEtapa = $this->despacharTextoEnEtapa($estado, $cliente, $m, $etapa)) {
            return $porEtapa;
        }

        return $this->transversal->menuRescate($cliente);
    }

    protected function despacharTextoEnEtapa(
        ConversationState $estado,
        Customer $cliente,
        string $mensaje,
        ?string $etapa
    ): ?RespuestaBot {
        if ($etapa === EtapaVentas::CONFIRMAR_REINICIO) {
            if (preg_match('/\b(s[ií]|dale|ok|confirmo|empezar)\b/u', $mensaje)) {
                $this->transversal->reiniciar($estado);

                return $this->inicio->mostrarCategorias($cliente, $estado);
            }
            if (preg_match('/\b(no|cancelar|seguir)\b/u', $mensaje)) {
                $this->maquina->salirConfirmarReinicio($estado);

                return RespuestaBot::texto($this->mensajes->plantilla('reinicio_cancelado'));
            }

            return $this->transversal->pedirConfirmarReinicio();
        }

        $ctx = $estado->context ?? [];
        $productoId = (int) ($ctx['producto_actual_id'] ?? $ctx['current_product_id'] ?? 0);
        $producto = $this->catalogo->productoVendible($productoId);

        if ($producto && in_array($etapa, [EtapaVentas::PRODUCTO, EtapaVentas::COLOR, EtapaVentas::TALLA], true)) {
            $colorActual = (string) ($ctx['color_actual'] ?? $ctx['current_color'] ?? '');

            if ($colorActual !== '') {
                $talla = $this->resolverTallaDesdeTexto($producto, $colorActual, $mensaje);
                if ($talla !== null) {
                    return $this->presentacion->elegirTalla($estado, $producto, $talla);
                }
            }

            $color = ResolvedorColorVariante::resolve($productoId, $mensaje);
            if ($color !== null) {
                return $this->presentacion->elegirColor($estado, $producto, $color);
            }

            if ($etapa === EtapaVentas::PRODUCTO) {
                return $this->presentacion->mostrarProducto($estado, $producto);
            }

            return RespuestaBot::texto($this->mensajes->plantilla('reprompt_etapa'));
        }

        if ($etapa === EtapaVentas::MAS_O_CONFIRMAR) {
            return $this->presentacion->reMostrarMasOConfirmar($estado);
        }

        if ($etapa === EtapaVentas::PRODUCTOS) {
            $catId = (int) ($ctx['categoria_actual_id'] ?? 0);
            if ($catId > 0) {
                return $this->navegacion->listarProductos($estado, $catId, 0);
            }
        }

        if ($etapa === EtapaVentas::CATEGORIA) {
            return $this->inicio->mostrarCategorias($cliente, $estado);
        }

        return null;
    }

    protected function elegirTallaPorIndice(ConversationState $estado, int $indice): RespuestaBot
    {
        $ctx = $estado->context ?? [];
        $tallas = $ctx['tallas_opciones'] ?? [];
        if (! isset($tallas[$indice])) {
            return RespuestaBot::texto($this->mensajes->plantilla('reprompt_etapa'));
        }

        $productoId = (int) ($ctx['producto_actual_id'] ?? 0);
        $producto = $this->catalogo->productoVendible($productoId);
        if (! $producto) {
            return RespuestaBot::texto(config('copy_ventas.sin_datos_bd'));
        }

        return $this->presentacion->elegirTalla($estado, $producto, (string) $tallas[$indice]);
    }

    protected function resolverTallaDesdeTexto(
        \App\Models\Product $producto,
        string $color,
        string $mensaje
    ): ?string {
        $stock = $this->catalogo->stockTallasDeColor($producto, $color);
        $mensajeNorm = mb_strtoupper(trim($mensaje));

        foreach (array_keys($stock) as $talla) {
            if (mb_strtoupper((string) $talla) === $mensajeNorm) {
                return (string) $talla;
            }
        }

        foreach (array_keys($stock) as $talla) {
            $tNorm = mb_strtoupper((string) $talla);
            if (str_contains($mensajeNorm, $tNorm) || str_contains($tNorm, $mensajeNorm)) {
                return (string) $talla;
            }
        }

        return null;
    }

    protected function esEtapaCheckout(?string $etapa): bool
    {
        return in_array($etapa, [
            EtapaVentas::ENVIO_METODO,
            EtapaVentas::ENVIO_DATOS,
            EtapaVentas::DATOS_REUTILIZAR,
            EtapaVentas::RESUMEN,
            EtapaVentas::PAGO,
            EtapaVentas::COMPROBANTE,
            EtapaVentas::TARJETA_DATOS,
            EtapaVentas::VALIDACION_PAGO,
        ], true);
    }
}
