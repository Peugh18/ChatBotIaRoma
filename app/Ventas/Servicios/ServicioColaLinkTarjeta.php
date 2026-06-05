<?php

namespace App\Ventas\Servicios;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Order;
use App\Services\ServicioLinkPagoTarjeta;
use App\Ventas\MaquinaEstados\EtapaVentas;
use Illuminate\Support\Collection;

class ServicioColaLinkTarjeta
{
    public function __construct(
        protected ServicioLinkPagoTarjeta $linkTarjeta,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pendientes(int $limite = 20): Collection
    {
        $estados = ConversationState::query()
            ->with('customer')
            ->where('context->pendiente_link_tarjeta', true)
            ->where(function ($q) {
                $q->where('context->etapa_venta', EtapaVentas::ESPERANDO_LINK_TARJETA)
                    ->orWhere('context->sales_stage', EtapaVentas::ESPERANDO_LINK_TARJETA);
            })
            ->orderByDesc('last_activity_at')
            ->limit($limite * 2)
            ->get();

        $items = collect();

        foreach ($estados as $estado) {
            if (! $this->linkTarjeta->estaPendiente($estado)) {
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
                'waiting_since' => $ctx[ServicioLinkPagoTarjeta::CTX_SOLICITADO_AT] ?? $estado->last_activity_at?->toIso8601String(),
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
