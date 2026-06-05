<?php

namespace App\Ventas\MaquinaEstados;

use App\Models\ConversationState;

class MaquinaEstadosVentas
{
    public const CLAVE = 'etapa_venta';

    public function obtener(ConversationState $estado): ?string
    {
        $ctx = $estado->context ?? [];
        $etapa = $ctx[self::CLAVE] ?? $ctx['sales_stage'] ?? null;
        if ($etapa === null) {
            return null;
        }

        return EtapaVentas::LEGACY_MAP[$etapa] ?? $etapa;
    }

    public function establecer(ConversationState $estado, ?string $etapa): void
    {
        $ctx = $estado->context ?? [];
        if ($etapa === null) {
            unset($ctx[self::CLAVE], $ctx['sales_stage']);
        } else {
            $ctx[self::CLAVE] = $etapa;
            unset($ctx['sales_stage']);
        }
        $estado->context = $ctx;
        $estado->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function carrito(ConversationState $estado): array
    {
        return $estado->context['carrito'] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public function guardarCarrito(ConversationState $estado, array $lineas): void
    {
        $ctx = $estado->context ?? [];
        $ctx['carrito'] = $lineas;
        unset($ctx['ultimo_pedido_id'], $ctx['last_order_id']);
        $estado->context = $ctx;
        $estado->save();
    }

    /** Sale de «¿borrar carrito?» sin reiniciar — evita bucle al pulsar No. */
    public function salirConfirmarReinicio(ConversationState $estado): void
    {
        $etapa = $this->carrito($estado) !== []
            ? EtapaVentas::MAS_O_CONFIRMAR
            : EtapaVentas::CATEGORIA;
        $this->establecer($estado, $etapa);
    }

    public function finalizarValidacionPago(ConversationState $estado): void
    {
        $ctx = $estado->context ?? [];
        unset(
            $ctx['payment_proof_url'],
            $ctx['payment_validation_requested_at'],
            $ctx['handoff'],
            $ctx['datos_envio'],
            $ctx['carrito'],
            $ctx['producto_actual_id'],
            $ctx['current_product_id'],
            $ctx['color_actual'],
            $ctx['current_color'],
            $ctx['talla_actual'],
            $ctx['current_size'],
            $ctx['checkout_paso'],
            $ctx['costo_envio'],
            $ctx['tallas_opciones'],
            $ctx['pending_interactive'],
            $ctx['pending_image_url'],
            $ctx['pending_image_caption'],
            $ctx['ultimo_pedido_id'],
            $ctx['last_order_id'],
            $ctx['pendiente_link_tarjeta'],
            $ctx['link_tarjeta_solicitado_at'],
            $ctx['payment_link_sent_at'],
            $ctx['payment_link_url'],
        );
        $estado->context = $ctx;
        $this->establecer($estado, EtapaVentas::INICIO);
    }

    public function reiniciarCarrito(ConversationState $estado): void
    {
        $ctx = $estado->context ?? [];
        unset(
            $ctx['carrito'],
            $ctx['producto_actual_id'],
            $ctx['color_actual'],
            $ctx['talla_actual'],
            $ctx['checkout_paso'],
            $ctx['costo_envio'],
            $ctx['ultimo_pedido_id'],
            $ctx['last_order_id'],
        );
        $estado->context = $ctx;
        $this->establecer($estado, EtapaVentas::INICIO);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosEnvio(ConversationState $estado): array
    {
        return $estado->context['datos_envio'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardarDatosEnvio(ConversationState $estado, array $datos): void
    {
        $ctx = $estado->context ?? [];
        $ctx['datos_envio'] = array_merge($ctx['datos_envio'] ?? [], $datos);
        $estado->context = $ctx;
        $estado->save();
    }

    public function limpiarDatosEnvio(ConversationState $estado): void
    {
        $ctx = $estado->context ?? [];
        unset($ctx['datos_envio'], $ctx['costo_envio'], $ctx['checkout_paso']);
        $estado->context = $ctx;
        $estado->save();
    }

    /**
     * @param  list<string>  $claves
     */
    public function quitarCamposEnvio(ConversationState $estado, array $claves): void
    {
        $ctx = $estado->context ?? [];
        $datos = $ctx['datos_envio'] ?? [];
        foreach ($claves as $clave) {
            unset($datos[$clave]);
        }
        $ctx['datos_envio'] = $datos;
        $estado->context = $ctx;
        $estado->save();
    }

    public function checkoutPaso(ConversationState $estado): ?string
    {
        return $estado->context['checkout_paso'] ?? null;
    }

    public function establecerCheckoutPaso(ConversationState $estado, ?string $paso): void
    {
        $ctx = $estado->context ?? [];
        if ($paso === null) {
            unset($ctx['checkout_paso']);
        } else {
            $ctx['checkout_paso'] = $paso;
        }
        $estado->context = $ctx;
        $estado->save();
    }

    public function costoEnvio(ConversationState $estado): float
    {
        return (float) ($estado->context['costo_envio'] ?? 0);
    }

    public function guardarCostoEnvio(ConversationState $estado, float $costo): void
    {
        $ctx = $estado->context ?? [];
        $ctx['costo_envio'] = $costo;
        $estado->context = $ctx;
        $estado->save();
    }

    /**
     * @return list<string>
     */
    public function etapasConRecordatorio(): array
    {
        return [
            EtapaVentas::COLOR,
            EtapaVentas::TALLA,
            EtapaVentas::MAS_O_CONFIRMAR,
            EtapaVentas::ENVIO_METODO,
            EtapaVentas::ENVIO_DATOS,
            EtapaVentas::ESPERANDO_LINK_TARJETA,
            EtapaVentas::RESUMEN,
            EtapaVentas::PAGO,
            EtapaVentas::COMPROBANTE,
        ];
    }
}
