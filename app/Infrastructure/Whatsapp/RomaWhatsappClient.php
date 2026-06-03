<?php

namespace App\Infrastructure\Whatsapp;

use App\Support\ContratoMensajeWhatsapp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RomaWhatsappClient
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url = config('services.roma.url') ?? '';
        $this->token = config('services.roma.token') ?? '';
    }

    /**
     * Envía mensaje vía roma-api (texto, imagen, interactivo, plantilla).
     */
    public function sendMessage(string $phone, string $body, string $waId, ?string $imageUrl = null, ?array $metadata = null): array
    {
        $romaUrl = rtrim($this->url, '/') . '/api/messages';
        $payload = ContratoMensajeWhatsapp::buildOutbound($phone, $body, $waId, $imageUrl, $metadata);

        Log::info('RomaWhatsappClient: Sending message', [
            'phone' => $phone,
            'waId' => $waId,
            'url' => $romaUrl,
            'type' => $payload['type'] ?? 'text',
            'has_interactive' => isset($payload['interactive']),
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'ngrok-skip-browser-warning' => 'true',
        ];

        if (!empty($this->token)) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
            $headers['X-Roma-Sync-Token'] = $this->token;
        }

        $response = Http::withHeaders($headers)
            ->timeout(20)
            ->connectTimeout(10)
            ->post($romaUrl, $payload);

        $json = $response->json();
        if (!$response->successful()) {
            Log::error('RomaWhatsappClient: API Error response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $detail = is_array($json) ? ($json['error'] ?? $json['message'] ?? $response->body()) : $response->body();
            throw new \Exception('Roma API send failed (' . $response->status() . '): ' . (is_string($detail) ? $detail : json_encode($detail)));
        }

        if (is_array($json) && array_key_exists('ok', $json) && $json['ok'] === false) {
            $detail = $json['error'] ?? $json['meta_error'] ?? $json['message'] ?? 'Meta rejected the message';
            Log::error('RomaWhatsappClient: Meta returned ok=false', ['response' => $json]);
            throw new \Exception('Meta/WhatsApp rejected message: ' . (is_string($detail) ? $detail : json_encode($detail)));
        }

        return is_array($json) ? $json : [];
    }
}
