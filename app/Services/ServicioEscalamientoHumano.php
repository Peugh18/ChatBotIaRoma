<?php

namespace App\Services;

use App\Models\AgentHandoff;
use App\Models\ConversationState;
use Illuminate\Support\Facades\Log;

/**
 * Deriva una conversación a un asesor humano (sin depender del flujo de ventas).
 */
class ServicioEscalamientoHumano
{
    public function escalar(ConversationState $estado, string $motivo): array
    {
        Log::info('ServicioEscalamientoHumano: escalando', [
            'telefono' => $estado->phone_number,
            'motivo' => $motivo,
        ]);

        $contexto = $estado->context ?? [];
        $contexto['handoff'] = app(ServicioContextoEscalamiento::class)->build($estado, $motivo);
        $estado->context = $contexto;

        $estado->update([
            'requires_human' => true,
            'is_auto_escalated' => true,
            'last_human_activity_at' => now(),
        ]);

        AgentHandoff::create([
            'conversation_state_id' => $estado->id,
            'reason' => $motivo,
            'requested_at' => now(),
        ]);

        return [
            'escalated' => true,
            'message' => 'Se ha asignado la conversación a un asesor humano. El bot dejará de responder automáticamente.',
        ];
    }
}
