<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\Message;
use App\Models\Order;

/**
 * Empaqueta contexto para escalamiento humano (patrón boundary handoff).
 */
class HandoffContextService
{
    public function build(ConversationState $state, string $reason): array
    {
        $ctx = $state->context ?? [];
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

        $summary = $this->summarize($ctx, $reason);

        return [
            'reason' => $reason,
            'summary' => $summary,
            'sales_stage' => $ctx['sales_stage'] ?? null,
            'current_product' => [
                'id' => $ctx['current_product_id'] ?? null,
                'name' => $ctx['current_product_name'] ?? null,
                'color' => $ctx['current_color'] ?? null,
                'size' => $ctx['current_size'] ?? null,
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

    protected function summarize(array $ctx, string $reason): string
    {
        $parts = ["Escalamiento: {$reason}."];
        if (!empty($ctx['current_product_name'])) {
            $parts[] = 'Vestido: ' . $ctx['current_product_name'];
        }
        if (!empty($ctx['current_color'])) {
            $parts[] = 'Color: ' . $ctx['current_color'];
        }
        if (!empty($ctx['sales_stage'])) {
            $parts[] = 'Etapa venta: ' . $ctx['sales_stage'];
        }

        return implode(' ', $parts);
    }
}
