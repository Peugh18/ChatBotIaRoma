<?php

namespace App\Support;

/**
 * Logging seguro: redacta PII y secretos en payloads de webhook.
 */
class SafeLog
{
    public static function redactPayload(array $payload): array
    {
        $safe = $payload;
        $keys = ['token', 'authorization', 'password', 'groq_api_key', 'secret'];

        foreach ($keys as $key) {
            if (isset($safe[$key])) {
                $safe[$key] = '[REDACTED]';
            }
        }

        foreach (['from', 'phone_number', 'sender_phone', 'to'] as $phoneKey) {
            if (!empty($safe[$phoneKey]) && is_string($safe[$phoneKey])) {
                $safe[$phoneKey] = self::maskPhone($safe[$phoneKey]);
            }
        }

        if (isset($safe['text']) && is_string($safe['text']) && mb_strlen($safe['text']) > 120) {
            $safe['text'] = mb_substr($safe['text'], 0, 120) . '…';
        }

        if (isset($safe['content']) && is_string($safe['content']) && mb_strlen($safe['content']) > 120) {
            $safe['content'] = mb_substr($safe['content'], 0, 120) . '…';
        }

        return $safe;
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 4) {
            return '***';
        }

        return '***' . substr($digits, -4);
    }
}
