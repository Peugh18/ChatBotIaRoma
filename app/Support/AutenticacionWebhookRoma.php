<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Autenticación de webhooks Roma: token + firma HMAC opcional.
 */
class AutenticacionWebhookRoma
{
    public static function extractToken(Request $request): ?string
    {
        $token = $request->header('X-Roma-Sync-Token')
            ?? $request->header('Authorization');

        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        return $token ?: $request->input('token');
    }

    public static function verify(Request $request): bool
    {
        $expected = (string) config('services.roma.token');
        if ($expected === '') {
            return false;
        }

        $token = self::extractToken($request);
        if ($token === null || !hash_equals($expected, $token)) {
            return false;
        }

        $secret = (string) config('services.roma.webhook_secret');
        if ($secret === '') {
            return true;
        }

        $signature = $request->header('X-Roma-Signature')
            ?? $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $payload = $request->getContent();
        if ($payload === '') {
            $payload = json_encode($request->all(), JSON_UNESCAPED_UNICODE) ?: '';
        }

        $expectedSig = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSig, $signature);
    }
}
