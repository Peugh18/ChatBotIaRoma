<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Normaliza URLs de imagen entrantes: roma-api (/inbound-media) o Meta (lookaside).
 */
class ServicioDescargaImagenWhatsapp
{
    public function esUrlMeta(string $url): bool
    {
        return str_contains($url, 'lookaside.fbsbx.com')
            || str_contains($url, 'graph.facebook.com')
            || str_contains($url, 'fbcdn.net');
    }

    public function esUrlRomaApiPublica(string $url): bool
    {
        if (str_contains($url, '/inbound-media/') || str_contains($url, '/api/media/file/')) {
            return true;
        }

        $base = rtrim((string) config('services.roma.api_public_url'), '/');
        if ($base !== '' && str_starts_with($url, $base)) {
            return true;
        }

        return false;
    }

    public function tokenConfigurado(): bool
    {
        $token = config('services.roma.wa_token');

        return is_string($token) && $token !== '';
    }

    /**
     * Resuelve imagen entrante (lookaside → local) usando roma-api y/o WA_TOKEN + media_id.
     *
     * @param  array<string, mixed>  $payload  Payload CRM (image_url, wa_id, raw)
     */
    public function resolverDesdePayloadInbound(array $payload): ?string
    {
        $url = trim((string) ($payload['image_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if ($this->esUrlRomaApiPublica($url)) {
            return $this->espejarDesdeRomaApi($url);
        }

        if (! $this->esUrlMeta($url)) {
            return $url;
        }

        $viaApi = $this->resolverViaRomaApi($payload);
        if ($viaApi) {
            return $viaApi;
        }

        $raw = $payload['raw'] ?? $payload['whatsapp_raw'] ?? null;
        if (is_array($raw)) {
            $imageBlock = is_array($raw['image'] ?? null) ? $raw['image'] : [];
            $mediaId = $imageBlock['id'] ?? null;
            if (is_string($mediaId) && $mediaId !== '' && $this->tokenConfigurado()) {
                $graphUrl = $this->obtenerUrlDescargaGraph($mediaId);
                if ($graphUrl) {
                    $local = $this->descargarAMediaPublica($graphUrl);
                    if ($local) {
                        Log::info('ServicioDescargaImagenWhatsapp: imagen descargada vía Graph media_id');

                        return $local;
                    }
                }
            }
        }

        return $this->resolverParaProcesamiento($url);
    }

    /**
     * URL lista para Voyage/match: espeja roma-api al CRM o descarga Meta con WA_TOKEN.
     */
    public function resolverParaProcesamiento(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if ($this->esUrlRomaApiPublica($url)) {
            return $this->espejarDesdeRomaApi($url);
        }

        if (! $this->esUrlMeta($url)) {
            return $url;
        }

        if (! $this->tokenConfigurado()) {
            Log::warning('ServicioDescargaImagenWhatsapp: imagen Meta sin WA_TOKEN en CRM', [
                'hint' => 'Añade WA_TOKEN en .env (mismo meta_access_token de Supabase) o despliega roma-api con /api/media/resolve-inbound y ROMA_API_PUBLIC_URL',
            ]);

            return null;
        }

        return $this->descargarAMediaPublica($url);
    }

    /**
     * Pide a roma-api (otra PC) descargar la imagen y devolver URL /inbound-media/.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolverViaRomaApi(array $payload): ?string
    {
        $base = rtrim((string) config('services.roma.url'), '/');
        if ($base === '') {
            return null;
        }

        $waId = (string) ($payload['wa_id'] ?? $payload['message_id'] ?? '');
        if ($waId === '') {
            return null;
        }

        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'ngrok-skip-browser-warning' => 'true',
                'User-Agent' => 'RomaCrm/1.0',
            ];
            $token = config('services.roma.token');
            if (is_string($token) && $token !== '') {
                $headers['X-Roma-Sync-Token'] = $token;
            }

            $response = Http::timeout(45)->withHeaders($headers)->post("{$base}/api/media/resolve-inbound", [
                'wa_id' => $waId,
                'image_url' => $payload['image_url'] ?? null,
                'raw' => $payload['raw'] ?? $payload['whatsapp_raw'] ?? null,
            ]);

            if (! $response->successful()) {
                Log::warning('ServicioDescargaImagenWhatsapp: roma-api resolve-inbound falló', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                    'hint' => 'En la otra PC: git pull, ROMA_API_PUBLIC_URL, reiniciar npm run dev',
                ]);

                return null;
            }

            $publicUrl = $response->json('public_url');
            if (! is_string($publicUrl) || $publicUrl === '') {
                return null;
            }

            if ($this->esUrlRomaApiPublica($publicUrl)) {
                return $this->espejarDesdeRomaApi($publicUrl);
            }

            return $publicUrl;
        } catch (\Exception $e) {
            Log::error('ServicioDescargaImagenWhatsapp: error llamando roma-api', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function obtenerUrlDescargaGraph(string $mediaId): ?string
    {
        if (! $this->tokenConfigurado()) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.roma.wa_token'))
                ->timeout(20)
                ->get('https://graph.facebook.com/v21.0/'.$mediaId);

            if (! $response->successful()) {
                Log::warning('ServicioDescargaImagenWhatsapp: Graph media metadata falló', [
                    'media_id' => $mediaId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $url = $response->json('url');

            return is_string($url) && $url !== '' ? $url : null;
        } catch (\Exception $e) {
            Log::error('ServicioDescargaImagenWhatsapp: Graph error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Descarga bytes de roma-api (ngrok) al storage público del CRM.
     */
    public function descargarDesdeUrlPublica(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'ngrok-skip-browser-warning' => 'true',
                'Accept' => 'image/*,*/*',
                'User-Agent' => 'RomaCrm/1.0',
            ])->timeout(45)->get($this->normalizarUrlMediaRomaApi($url));

            if (! $response->successful()) {
                Log::warning('ServicioDescargaImagenWhatsapp: no se pudo descargar desde roma-api', [
                    'url' => $this->normalizarUrlMediaRomaApi($url),
                    'status' => $response->status(),
                    'hint' => 'Actualiza roma-api: GET /api/media/file/[nombre] y reinicia npm run dev',
                ]);

                return null;
            }

            $contentType = (string) $response->header('Content-Type');
            if (str_contains($contentType, 'text/html')) {
                Log::warning('ServicioDescargaImagenWhatsapp: respuesta HTML en lugar de imagen (¿ngrok interstitial?)', [
                    'url' => $url,
                ]);

                return null;
            }

            $ext = 'jpg';
            if (str_contains($contentType, 'png')) {
                $ext = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $ext = 'webp';
            }

            $filename = 'customers/media/'.uniqid('romaapi_').'.'.$ext;
            Storage::disk('public')->put($filename, $response->body());

            Log::info('ServicioDescargaImagenWhatsapp: imagen descargada desde roma-api', [
                'source' => $url,
            ]);

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            Log::error('ServicioDescargaImagenWhatsapp: error descargando URL pública', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Convierte /inbound-media/x.jpg → /api/media/file/x.jpg si aplica. */
    public function normalizarUrlMediaRomaApi(string $url): string
    {
        if (preg_match('#/inbound-media/([^/?]+)#', $url, $m)) {
            $base = preg_replace('#/inbound-media/.*$#', '', $url);

            return $base.'/api/media/file/'.$m[1];
        }

        return $url;
    }

    public function espejarDesdeRomaApi(string $publicUrl): ?string
    {
        return $this->descargarDesdeUrlPublica($publicUrl);
    }

    public function descargarAMediaPublica(string $metaUrl): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.roma.wa_token'),
                'User-Agent' => 'curl/7.68.0',
            ])->timeout(30)->get($metaUrl);

            if (! $response->successful()) {
                Log::warning('ServicioDescargaImagenWhatsapp: descarga Meta fallida', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $ext = 'jpg';
            $mime = $response->header('Content-Type') ?? 'image/jpeg';
            if (str_contains($mime, 'png')) {
                $ext = 'png';
            } elseif (str_contains($mime, 'webp')) {
                $ext = 'webp';
            }

            $filename = 'customers/media/'.uniqid('wa_').'.'.$ext;
            Storage::disk('public')->put($filename, $response->body());

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            Log::error('ServicioDescargaImagenWhatsapp: error', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
