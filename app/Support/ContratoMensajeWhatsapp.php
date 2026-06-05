<?php

namespace App\Support;

use App\Infrastructure\Whatsapp\WhatsappInteractiveNormalizer;

/**
 * Contrato compartido RomaCrm ↔ roma-api (WhatsApp Business Cloud API).
 */
class ContratoMensajeWhatsapp
{
    public const VERSION = 1;

    public const SOURCE = 'laravel_crm';

    public const OUTBOUND_TYPES = ['text', 'image', 'interactive', 'template'];

    public const INBOUND_INTERACTIVE_TYPES = [
        'interactive_button_reply',
        'interactive_list_reply',
    ];

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public static function buildOutbound(
        string $phone,
        string $body,
        string $waId,
        ?string $imageUrl = null,
        ?array $metadata = null
    ): array {
        $metadata = $metadata ?? [];
        $type = $metadata['type'] ?? 'text';
        if ($imageUrl && $type === 'text') {
            $type = 'image';
        }

        $payload = [
            'to' => $phone,
            'type' => $type,
            'context' => [
                'source' => self::SOURCE,
                'message_id' => $waId,
            ],
            'roma_contract_version' => self::VERSION,
        ];

        if ($type === 'text') {
            $payload['text'] = ['body' => $body];
            $payload['wa_id'] = $waId;
            $payload['sender_phone'] = $phone;
            $payload['message_body'] = $body;
            $payload['direction'] = 'outbound';
            if ($imageUrl) {
                $payload['image_url'] = $imageUrl;
            }
        } elseif ($type === 'image') {
            $payload['image'] = [
                'link' => $imageUrl ?? ($metadata['image_url'] ?? ''),
                'caption' => $body ?: ($metadata['image_caption'] ?? ''),
            ];
        } elseif ($type === 'interactive') {
            $interactive = $metadata['interactive'] ?? [];
            $canAttachImageHeader = $imageUrl && self::isPublicHttpsUrl($imageUrl);
            if (isset($interactive['kind'])) {
                if ($canAttachImageHeader && empty($interactive['header'])) {
                    $interactive['header'] = [
                        'type' => 'image',
                        'image' => ['link' => $imageUrl],
                    ];
                }
                $payload['interactive'] = $interactive;
            } else {
                $normalizer = app(WhatsappInteractiveNormalizer::class);
                $metaInteractive = $normalizer->toMetaPayload($interactive);
                if ($metaInteractive !== null && ! empty($metaInteractive['action'])) {
                    if ($canAttachImageHeader && empty($metaInteractive['header'])) {
                        $metaInteractive['header'] = [
                            'type' => 'image',
                            'image' => ['link' => $imageUrl],
                        ];
                    }
                    $payload['interactive'] = $metaInteractive;
                } else {
                    $payload['type'] = 'text';
                    $payload['text'] = ['body' => $body ?: 'Elige una opción escribiendo el número.'];
                }
            }
        } elseif ($type === 'template') {
            $payload['template'] = $metadata['template'] ?? [];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    /**
     * roma-api a veces manda message_type=text pero raw.type=location.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function esMensajeUbicacion(array $payload): bool
    {
        if (($payload['message_type'] ?? '') === 'location') {
            return true;
        }

        if (! empty($payload['location']) && is_array($payload['location'])) {
            return true;
        }

        $raw = $payload['raw'] ?? null;

        return is_array($raw)
            && ($raw['type'] ?? '') === 'location'
            && is_array($raw['location'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function ubicacionCrudaDePayload(array $payload): ?array
    {
        $loc = $payload['location'] ?? null;
        if (! is_array($loc)) {
            $raw = $payload['raw'] ?? $payload['whatsapp_raw'] ?? null;
            if (is_array($raw) && is_array($raw['location'] ?? null)) {
                $loc = $raw['location'];
            }
        }

        return is_array($loc) ? $loc : null;
    }

    public static function inboundMetadata(array $payload): array
    {
        $messageType = $payload['message_type'] ?? 'text';
        if (self::esMensajeUbicacion($payload)) {
            $messageType = 'location';
        }

        $meta = [
            'roma_contract_version' => self::VERSION,
            'whatsapp_message_type' => $messageType,
        ];

        if (! empty($payload['interactive']) && is_array($payload['interactive'])) {
            $meta['interactive'] = $payload['interactive'];
        }

        if (in_array($messageType, self::INBOUND_INTERACTIVE_TYPES, true)) {
            $meta['type'] = 'interactive_reply';
        } elseif ($messageType === 'image') {
            $meta['type'] = 'image';
            if (! empty($payload['image_url'])) {
                $meta['image_url'] = $payload['image_url'];
            }
        } elseif ($messageType === 'location') {
            $meta['type'] = 'location';
        }

        if (! empty($payload['raw']) && is_array($payload['raw'])) {
            $meta['whatsapp_raw'] = $payload['raw'];
        }

        $loc = self::ubicacionCrudaDePayload($payload);
        if ($loc !== null) {
            $meta['location'] = $loc;
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{lat: float, lng: float, name?: string}|null
     */
    public static function ubicacionDesdePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $loc = self::ubicacionCrudaDePayload($payload);
        if ($loc === null) {
            return null;
        }

        $lat = $loc['latitude'] ?? $loc['lat'] ?? null;
        $lng = $loc['longitude'] ?? $loc['lng'] ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }

        $resultado = ['lat' => (float) $lat, 'lng' => (float) $lng];
        $nombre = $loc['name'] ?? $loc['address'] ?? null;
        if (is_string($nombre) && $nombre !== '') {
            $resultado['name'] = $nombre;
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function inboundContent(array $payload): string
    {
        $raw = $payload['text'] ?? $payload['content'] ?? $payload['message_body'] ?? '';
        if (is_array($raw) && isset($raw['body'])) {
            $raw = $raw['body'];
        }
        $content = is_string($raw) ? trim($raw) : '';
        if (self::esMensajeUbicacion($payload)) {
            return '📍 Ubicación compartida';
        }
        if ($content === '' || $content === '[non-text]') {
            if (! empty($payload['interactive']['title'])) {
                return (string) $payload['interactive']['title'];
            }
            if ($content === '[non-text]') {
                return '';
            }
        }

        return $content;
    }

    protected static function isPublicHttpsUrl(string $url): bool
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'];

        return ! in_array(strtolower($host), $blocked, true);
    }
}
