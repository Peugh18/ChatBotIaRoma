<?php

namespace App\Ventas\Repositorios;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;

class RepositorioPedidos
{
    /**
     * @param  list<array<string, mixed>>  $lineasCarrito
     * @param  array<string, mixed>  $datosEnvio
     */
    public function crearPendiente(
        ConversationState $estado,
        Customer $cliente,
        array $lineasCarrito,
        array $datosEnvio,
        float $costoEnvio,
        string $metodoPago
    ): Order {
        $subtotal = 0.0;
        foreach ($lineasCarrito as $l) {
            $subtotal += (float) ($l['precio'] ?? 0);
        }

        $metodoEnvio = (string) ($datosEnvio['metodo'] ?? 'motorizado');
        $total = $subtotal + $costoEnvio;

        $order = Order::create([
            'customer_id' => $cliente->id,
            'conversation_state_id' => $estado->id,
            'status' => 'pending',
            'shipping_method' => $metodoEnvio === 'shalom' ? 'shalom' : 'motorizado',
            'shipping_cost' => $costoEnvio,
            'payment_method' => $metodoPago === 'tarjeta' ? 'card' : 'yape',
            'district' => $datosEnvio['distrito'] ?? null,
            'full_address' => trim(
                ($datosEnvio['direccion'] ?? '').' '.($datosEnvio['referencia'] ?? '')
            ),
            'amount_subtotal' => $subtotal,
            'amount_total' => $total,
            'notes' => $datosEnvio['referencia'] ?? null,
        ]);

        foreach ($lineasCarrito as $l) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => (int) $l['producto_id'],
                'color' => $l['color'] ?? null,
                'size' => $l['talla'] ?? null,
                'qty' => 1,
                'unit_price' => (float) ($l['precio'] ?? 0),
                'total' => (float) ($l['precio'] ?? 0),
            ]);
        }

        $this->vincularPedidoEnEstado($estado, $order->id, $datosEnvio);

        return $order;
    }

    public function guardarComprobante(int $pedidoId, string $url): void
    {
        Order::where('id', $pedidoId)->update([
            'payment_proof_url' => $url,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datosEnvio
     */
    public function vincularPedidoEnEstado(ConversationState $estado, int $pedidoId, array $datosEnvio): void
    {
        $ctx = $estado->context ?? [];
        $ctx['ultimo_pedido_id'] = $pedidoId;
        $ctx['last_order_id'] = $pedidoId;
        $ctx['datos_envio'] = $datosEnvio;
        $estado->context = $ctx;
        $estado->save();
    }

    public function pedidoActivo(ConversationState $estado): ?Order
    {
        $id = (int) (($estado->context ?? [])['ultimo_pedido_id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        return Order::find($id);
    }
}
