<?php

namespace App\Services;

use App\Events\ConversationModeChanged;
use App\Models\ConversationState;
use Illuminate\Support\Facades\Log;

/**
 * Tras confirmar pago: asesor humano atiende hasta entrega (pipeline «Entregado»).
 */
class ServicioModoConversacionPedido
{
    public const CTX_ASESOR_POST_PEDIDO = 'asesor_post_pedido';

    public function activarHumanoTrasConfirmacion(ConversationState $estado, ?int $orderId = null): void
    {
        $ctx = $estado->context ?? [];
        $ctx[self::CTX_ASESOR_POST_PEDIDO] = true;
        if ($orderId !== null && $orderId > 0) {
            $ctx['asesor_post_pedido_order_id'] = $orderId;
        }

        $estado->update([
            'context' => $ctx,
            'requires_human' => true,
            'is_auto_escalated' => false,
            'last_human_activity_at' => now(),
        ]);

        Log::info('ServicioModoConversacionPedido: modo humano post-pedido', [
            'phone' => $estado->phone_number,
            'order_id' => $orderId,
        ]);

        $this->broadcastMode($estado->phone_number, true, true);
    }

    public function tieneAsesorPostPedido(ConversationState $estado): bool
    {
        return (bool) (($estado->context ?? [])[self::CTX_ASESOR_POST_PEDIDO] ?? false);
    }

    public function reactivarBotTrasEntrega(ConversationState $estado): void
    {
        $ctx = $estado->context ?? [];
        unset($ctx[self::CTX_ASESOR_POST_PEDIDO], $ctx['asesor_post_pedido_order_id']);
        $estado->update([
            'context' => $ctx,
            'requires_human' => false,
            'is_auto_escalated' => false,
            'last_human_activity_at' => null,
        ]);

        app(\App\Ventas\MaquinaEstados\MaquinaEstadosVentas::class)->finalizarValidacionPago($estado);

        Log::info('ServicioModoConversacionPedido: pedido entregado, vuelve modo bot y se reinicia flujo a 0', [
            'phone' => $estado->phone_number,
        ]);

        $this->broadcastMode($estado->phone_number, false, false);
    }

    protected function broadcastMode(string $phone, bool $human, bool $asesorPostPedido = false): void
    {
        if (env('BROADCAST_CONNECTION') !== 'pusher' || ! env('PUSHER_APP_ID')) {
            return;
        }

        try {
            broadcast(new ConversationModeChanged(
                $phone,
                $human ? 'human' : 'bot',
                false,
                $asesorPostPedido,
            ))->toOthers();
        } catch (\Exception $e) {
            Log::error('ServicioModoConversacionPedido: broadcast falló', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
