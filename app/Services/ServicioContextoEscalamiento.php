<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Message;
use App\Models\Order;
use App\Support\EtapaVenta;

/**
 * Empaqueta contexto para escalamiento humano (patrón boundary handoff).
 */
class ServicioContextoEscalamiento
{
    public function build(ConversationState $state, string $reason): array
    {
        $ctx = $state->context ?? [];
        $etapa = EtapaVenta::obtener($state);
        $recent = Message::where('phone_number', $state->phone_number)
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $transcript = $recent->map(fn (Message $m) => [
            'direction' => $m->direction,
            'content' => mb_substr((string) $m->content, 0, 500),
            'at' => $m->created_at?->toDateTimeString(),
        ])->all();

        $lastOrder = Order::where('customer_id', $state->customer_id)
            ->orderByDesc('id')
            ->first();

        $summary = $this->summarize($ctx, $reason, $etapa);

        return [
            'reason' => $reason,
            'summary' => $summary,
            'sales_stage' => $etapa,
            'etapa_venta' => $etapa,
            'current_product' => [
                'id' => $ctx['producto_actual_id'] ?? $ctx['current_product_id'] ?? null,
                'name' => $ctx['producto_actual_nombre'] ?? $ctx['current_product_name'] ?? null,
                'color' => $ctx['color_actual'] ?? $ctx['current_color'] ?? null,
                'size' => $ctx['talla_actual'] ?? $ctx['current_size'] ?? null,
            ],
            'last_order' => $lastOrder ? [
                'id' => $lastOrder->id,
                'status' => $lastOrder->status,
                'total' => (float) $lastOrder->amount_total,
            ] : null,
            'transcript' => $transcript,
            'escalated_at' => now()->toIso8601String(),
        ];
    }

    protected function summarize(array $ctx, string $reason, ?string $etapa): string
    {
        $parts = ["Escalamiento: {$reason}."];
        $nombre = $ctx['producto_actual_nombre'] ?? $ctx['current_product_name'] ?? null;
        $color = $ctx['color_actual'] ?? $ctx['current_color'] ?? null;
        if (! empty($nombre)) {
            $parts[] = 'Vestido: '.$nombre;
        }
        if (! empty($color)) {
            $parts[] = 'Color: '.$color;
        }
        if (! empty($etapa)) {
            $parts[] = 'Etapa venta: '.$etapa;
        }

        return implode(' ', $parts);
    }
}
