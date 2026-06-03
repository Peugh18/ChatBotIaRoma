<?php

namespace App\Ventas\Contratos;

/**
 * Respuesta unificada del bot hacia WhatsApp.
 */
class RespuestaBot
{
    public function __construct(
        public string $texto = '',
        public array $metadata = [],
        public ?string $urlImagen = null,
        public ?string $captionImagen = null,
        public bool $escalarHumano = false,
    ) {}

    public static function vacia(): self
    {
        return new self;
    }

    public static function texto(string $texto): self
    {
        return new self(texto: $texto);
    }

    /**
     * @param  array<string, mixed>  $interactivo
     */
    public static function conInteractivo(string $texto, array $interactivo): self
    {
        $texto = trim($texto);
        if ($texto === '' && isset($interactivo['body']['text'])) {
            $texto = trim((string) $interactivo['body']['text']);
        }

        return new self(
            texto: $texto,
            metadata: [
                'type' => 'interactive',
                'interactive' => $interactivo,
            ],
        );
    }

    /** Hay algo que enviar a WhatsApp (texto, botones/lista o imagen pendiente). */
    public function debeEnviar(): bool
    {
        if (trim($this->texto) !== '') {
            return true;
        }

        if (($this->metadata['type'] ?? '') === 'interactive' && ! empty($this->metadata['interactive'])) {
            return true;
        }

        return $this->urlImagen !== null && $this->urlImagen !== '';
    }

    public function conImagen(string $url, ?string $caption = null): self
    {
        $this->urlImagen = $url;
        $this->captionImagen = $caption;

        return $this;
    }

    public function marcarEscalamientoHumano(): self
    {
        $this->escalarHumano = true;

        return $this;
    }

    public function prefijarTexto(string $prefijo): self
    {
        $prefijo = trim($prefijo);
        if ($prefijo !== '') {
            $this->texto = $this->texto !== ''
                ? $prefijo."\n\n".$this->texto
                : $prefijo;
        }

        return $this;
    }

    /**
     * @return array{text: string, metadata: array}
     */
    public function aArray(): array
    {
        $meta = $this->metadata;
        if ($this->escalarHumano) {
            $meta['trigger_human_escalation'] = true;
        }
        if ($this->urlImagen) {
            $meta['pending_image_url'] = $this->urlImagen;
            $meta['pending_image_caption'] = $this->captionImagen ?? '📸';
        }

        return [
            'text' => $this->texto,
            'metadata' => $meta,
        ];
    }
}
