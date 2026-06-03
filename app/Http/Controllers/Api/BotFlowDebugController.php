<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationState;
use App\Services\ServicioBotEntrada;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simula mensajes del bot sin enviar WhatsApp (solo equipo interno).
 */
class BotFlowDebugController extends Controller
{
    public function simulate(Request $request, ServicioBotEntrada $bot, MaquinaEstadosVentas $maquina): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'message' => 'nullable|string|max:4096',
            'image_url' => 'nullable|string|max:2048',
            'metadata' => 'nullable|array',
        ]);

        $telefono = preg_replace('/\D/', '', $validated['phone']);
        if (strlen($telefono) < 9) {
            return response()->json(['message' => 'Teléfono inválido'], 422);
        }

        $mensaje = trim((string) ($validated['message'] ?? ''));
        if ($mensaje === '' && empty($validated['image_url'])) {
            $mensaje = 'hola';
        }

        $resultado = $bot->procesar(
            $telefono,
            $mensaje,
            $validated['image_url'] ?? null,
            $validated['metadata'] ?? null
        );

        $estado = ConversationState::where('phone_number', $telefono)->first();

        return response()->json([
            'phone' => $telefono,
            'input' => [
                'message' => $mensaje,
                'image_url' => $validated['image_url'] ?? null,
            ],
            'response' => $resultado,
            'state' => [
                'etapa_venta' => $maquina->obtener($estado),
                'requires_human' => (bool) ($estado?->requires_human),
                'context' => $estado?->context ?? [],
            ],
        ]);
    }

    public function reset(Request $request, MaquinaEstadosVentas $maquina): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $telefono = preg_replace('/\D/', '', $validated['phone']);
        $estado = ConversationState::where('phone_number', $telefono)->first();

        if (! $estado) {
            return response()->json(['message' => 'Sin conversación para ese teléfono'], 404);
        }

        $maquina->reiniciarCarrito($estado);
        $estado->update([
            'requires_human' => false,
            'is_auto_escalated' => false,
        ]);

        return response()->json([
            'message' => 'Conversación reiniciada para pruebas',
            'phone' => $telefono,
        ]);
    }
}
