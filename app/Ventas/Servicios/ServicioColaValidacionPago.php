<?php

namespace App\Ventas\Servicios;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Order;
use App\Support\EtapaVenta;
use App\Ventas\MaquinaEstados\EtapaVentas;
use Illuminate\Support\Collection;

class ServicioColaValidacionPago
{
    /**
     * Conversaciones con comprobante Yape pendiente de validar humana.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pendientes(int $limite = 20): Collection
    {
        $estados = ConversationState::query()
            ->with('customer')
            ->where(function ($q) {
                $q->where('context->etapa_venta', EtapaVentas::VALIDACION_PAGO)
                    ->orWhere('context->etapa_venta', 'validacion_pago')
                    ->orWhere('context->sales_stage', EtapaVenta::LEGACY_VALIDACION_PAGO);
            })
            ->orderByDesc('last_activity_at')
            ->limit($limite * 3)
            ->get();

        $items = collect();

        foreach ($estados as $estado) {
            if (! EtapaVenta::esValidacionPago($estado)) {
                continue;
            }

            $ctx = $estado->context ?? [];
            $orderId = (int) ($ctx['ultimo_pedido_id'] ?? $ctx['last_order_id'] ?? 0);
            $order = $orderId > 0 ? Order::find($orderId) : null;

            if ($order && $order->status !== 'pending') {
                continue;
            }

            /** @var Customer|null $cliente */
            $cliente = $estado->customer;

            $items->push([
                'phone_number' => $estado->phone_number,
                'customer_name' => $cliente?->name,
                'conversation_state_id' => $estado->id,
                'order_id' => $orderId > 0 ? $orderId : null,
                'order_total' => $order ? (float) $order->amount_total : null,
                'payment_proof_url' => $order?->payment_proof_url,
                'waiting_since' => $estado->last_activity_at?->toIso8601String(),
            ]);

            if ($items->count() >= $limite) {
                break;
            }
        }

        return $items->values();
    }

    public function contar(): int
    {
        return $this->pendientes(50)->count();
    }
}
