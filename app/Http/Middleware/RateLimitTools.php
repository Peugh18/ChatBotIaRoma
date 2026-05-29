<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limit para webhooks de Roma API (por teléfono del cliente).
 * Los receipts de estado (sent/delivered/read) no consumen cupo.
 */
class RateLimitTools
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isStatusOnlyWebhook($request)) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request);

        // Límite: 60 mensajes entrantes por minuto por teléfono
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json([
                'error' => 'Demasiadas solicitudes. Por favor espera un momento.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }

    protected function isStatusOnlyWebhook(Request $request): bool
    {
        $payload = $request->all();

        if (($payload['event'] ?? null) === 'status') {
            return true;
        }

        $status = $payload['status'] ?? null;
        if (in_array($status, ['sent', 'delivered', 'read'], true)) {
            $hasBody = trim((string) ($payload['message_body'] ?? '')) !== '';
            $hasInteractive = ! empty($payload['interactive']);
            $isInboundType = in_array($payload['message_type'] ?? '', [
                'interactive_button_reply',
                'interactive_list_reply',
                'image',
            ], true);

            return ! $hasBody && ! $hasInteractive && ! $isInboundType;
        }

        return false;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $phone = $request->input('sender_phone')
            ?? $request->input('from')
            ?? $request->input('phone_number');

        if (is_string($phone) && $phone !== '') {
            return 'roma-webhook:'.$phone;
        }

        if ($request->has('messages')) {
            $messages = $request->input('messages');
            if (is_array($messages) && ! empty($messages[0]['phone_number'])) {
                return 'roma-webhook:'.$messages[0]['phone_number'];
            }
        }

        return 'roma-webhook:ip:'.$request->ip();
    }
}
