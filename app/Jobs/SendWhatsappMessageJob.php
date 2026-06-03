<?php

namespace App\Jobs;

use App\Models\Message;
use App\Infrastructure\Whatsapp\RomaWhatsappClient;
use App\Services\ServicioMediaProducto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número máximo de intentos.
     */
    public $tries = 5;

    /**
     * Segundos a esperar antes de reintentar.
     * Backoff exponencial: 5s, 30s, 120s
     */
    public function backoff(): array
    {
        return [5, 20, 60, 180, 420];
    }

    protected Message $message;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(RomaWhatsappClient $client, ServicioMediaProducto $media): void
    {
        Log::info('SendWhatsappMessageJob: Processing send', [
            'message_id' => $this->message->id,
            'phone' => $this->message->phone_number,
        ]);

        try {
            $waId = $this->message->message_id ?? ('out_' . uniqid());

            // Si es un message_id temporal, generamos uno real para el envío
            if (str_starts_with($waId, 'temp_') || str_starts_with($waId, 'out_') === false) {
                $waId = 'out_' . uniqid();
            }

            $metadata = $this->message->metadata ?? [];
            $imageUrl = $metadata['image_url'] ?? null;
            if ($imageUrl) {
                $imageUrl = $media->resolveWhatsappSendUrl($imageUrl);
                if ($media->isUrlReachableByMeta($imageUrl)) {
                    $metadata['image_url'] = $imageUrl;
                } else {
                    Log::warning('SendWhatsappMessageJob: omitting local image URL (Meta cannot fetch it)', [
                        'message_id' => $this->message->id,
                        'image_url' => $imageUrl,
                        'hint' => 'Set PUBLIC_APP_URL in .env to your HTTPS ngrok URL (CRM port 8000)',
                    ]);
                    $imageUrl = null;
                    unset($metadata['image_url']);
                    if (($metadata['type'] ?? 'text') === 'image') {
                        $metadata['type'] = 'text';
                    }
                }
            }

            $response = $client->sendMessage($this->message->phone_number, $this->message->content, $waId, $imageUrl, $metadata);
            $providerWaId = (string) ($response['wa_id'] ?? $response['message_id'] ?? '');

            // Consideramos exito solo cuando roma-api devuelve un wa_id real de Meta.
            // Si devuelve IDs internos (out_/msg_), evitamos falso "sent".
            if ($providerWaId === '' || !str_starts_with($providerWaId, 'wamid.')) {
                throw new \RuntimeException(
                    'Roma API accepted request without Meta wa_id. Response: ' . json_encode($response)
                );
            }

            $this->message->update([
                'message_id' => $providerWaId,
                'status' => 'sent',
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'roma_api_response' => $response,
                    'sent_via_job' => true,
                    'meta_wa_id' => $providerWaId,
                ]),
            ]);

            // Disparar evento Pusher para actualizar el frontend en tiempo real
            if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                try {
                    broadcast(new \App\Events\MessageReceived($this->message))->toOthers();
                } catch (\Exception $e) {
                    Log::error('SendWhatsappMessageJob: Error broadcasting sent message: ' . $e->getMessage());
                }
            }

            Log::info('SendWhatsappMessageJob: Sent successfully', ['id' => $this->message->id]);

        } catch (\Exception $e) {
            Log::error('SendWhatsappMessageJob: Send failed', [
                'id' => $this->message->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->isPermanentProviderError($e)) {
                $this->markAsFailed($e->getMessage(), true);
                return;
            }

            if ($this->shouldMarkAsFailedNow()) {
                $this->markAsFailed($e->getMessage(), false);
            }

            throw $e;
        }
    }

    /**
     * Laravel llama esto cuando el job agota reintentos del worker (p. ej. queue:listen --tries=1).
     */
    public function failed(?\Throwable $exception): void
    {
        $fresh = $this->message->fresh();
        if ($fresh && $fresh->status === 'pending') {
            $this->markAsFailed($exception?->getMessage() ?? 'No se pudo enviar el mensaje', true);
        }
    }

    protected function shouldMarkAsFailedNow(): bool
    {
        if ($this->attempts() >= $this->tries) {
            return true;
        }

        $maxTries = $this->job?->maxTries();
        if ($maxTries !== null && $this->attempts() >= $maxTries) {
            return true;
        }

        return false;
    }

    protected function isPermanentProviderError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unsupported post request')
            || str_contains($message, 'graphmethodexception')
            || str_contains($message, 'oauthexception')
            || str_contains($message, 'authentication error')
            || str_contains($message, '"code":190')
            || str_contains($message, 'error_subcode')
            || str_contains($message, '"code":100')
            || str_contains($message, '"code":131030')
            || str_contains($message, 'not in allowed list')
            || str_contains($message, 'no accesible para meta');
    }

    protected function humanizeSendError(string $error): string
    {
        if (str_contains($error, '131030') || str_contains($error, 'allowed list')) {
            return 'Este número no está en la lista de prueba de Meta. Agrégalo en developers.facebook.com → WhatsApp → API Setup.';
        }

        if (str_contains($error, '190') || str_contains(strtolower($error), 'authentication error')) {
            return 'Token de WhatsApp (Meta) inválido o expirado en roma-api. Renueva el access token en Meta / Kapso y vuelve a conectar el número.';
        }

        if (str_contains($error, 'PUBLIC_APP_URL') || str_contains($error, 'no accesible')) {
            return 'La foto usa una URL local. Pon PUBLIC_APP_URL en .env con tu ngrok del CRM (puerto 8000).';
        }

        return mb_strlen($error) > 280 ? mb_substr($error, 0, 280) . '…' : $error;
    }

    protected function markAsFailed(string $error, bool $isPermanent): void
    {
        $this->message->update([
            'status' => 'failed',
            'metadata' => array_merge($this->message->metadata ?? [], [
                'send_error' => $this->humanizeSendError($error),
                'send_error_raw' => $error,
                'provider_error_type' => $isPermanent ? 'permanent' : 'retry_exhausted',
            ]),
        ]);

        // Disparar evento Pusher para actualizar el estado a "failed" en tiempo real
        if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
            try {
                broadcast(new \App\Events\MessageReceived($this->message))->toOthers();
            } catch (\Exception $broadcastException) {
                Log::error('SendWhatsappMessageJob: Error broadcasting failed state: ' . $broadcastException->getMessage());
            }
        }
    }
}
