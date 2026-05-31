<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = BotSetting::firstOrCreate([], [
            'system_prompt' => 'Eres una asesora de ventas enfocada en cierre. Detecta intención de compra y guía al cliente a una decisión rápida con datos reales del catálogo.',
            'welcome_message' => '¡Hola! Soy el asistente de Roma. ¿Qué vestido estás buscando hoy? Puedes enviarme una foto o el nombre del vestido.',
            'reminder_3min_message' => 'Hermosa nos confirmas si deseas realizar el pedido para poder ayudarte.',
            'reminder_15min_message' => 'Muchas gracias hermosa, cualquier cosita si te animas más tarde nos escribes. Que tengas un gran día 🤗🤗',
            'escalation_message' => 'Voy a realizar la consulta a un agente especializado y en breve le brindamos una respuesta.',
            'auto_reply_enabled' => true,
            'model_chat' => 'llama-3.3-70b-versatile',
            'model_vision' => 'meta-llama/llama-4-scout-17b-16e-instruct',
            'reminder_3min_seconds' => 180,
            'reminder_15min_seconds' => 900,
        ]);

        return response()->json($this->maskSensitiveFields($settings));
    }

    public function update(Request $request): JsonResponse
    {
        $settings = BotSetting::firstOrFail();

        $validated = $request->validate([
            'system_prompt' => 'nullable|string',
            'welcome_message' => 'nullable|string',
            'reminder_3min_message' => 'nullable|string',
            'reminder_15min_message' => 'nullable|string',
            'escalation_message' => 'nullable|string',
            'auto_reply_enabled' => 'nullable|boolean',
            'groq_api_key' => 'nullable|string',
            'huggingface_token' => 'nullable|string',
            'model_chat' => 'nullable|string',
            'model_vision' => 'nullable|string',
            'reminder_3min_seconds' => 'nullable|integer',
            'reminder_15min_seconds' => 'nullable|integer',
        ]);

        if (isset($validated['groq_api_key']) && $this->isMaskedKey($validated['groq_api_key'])) {
            unset($validated['groq_api_key']);
        }

        if (isset($validated['huggingface_token']) && $this->isMaskedKey($validated['huggingface_token'])) {
            unset($validated['huggingface_token']);
        }

        $settings->update($validated);

        return response()->json($this->maskSensitiveFields($settings->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function maskSensitiveFields(BotSetting $settings): array
    {
        $data = $settings->toArray();

        if (! empty($data['groq_api_key'])) {
            $data['groq_api_key'] = $this->maskKey((string) $data['groq_api_key']);
        }

        if (! empty($data['huggingface_token'])) {
            $data['huggingface_token'] = $this->maskKey((string) $data['huggingface_token']);
        }

        return $data;
    }

    protected function maskKey(string $key): string
    {
        if (strlen($key) <= 4) {
            return '****';
        }

        return str_repeat('*', strlen($key) - 4).substr($key, -4);
    }

    protected function isMaskedKey(string $value): bool
    {
        return str_contains($value, '****') || preg_match('/^\*+[a-zA-Z0-9]{4}$/', $value) === 1;
    }
}
