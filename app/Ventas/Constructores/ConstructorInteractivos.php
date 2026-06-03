<?php

namespace App\Ventas\Constructores;

/**
 * Botones (máx 3) o lista WhatsApp según cantidad de opciones.
 */
class ConstructorInteractivos
{
    public function __construct(
        protected PaginadorListasWhatsapp $paginador,
    ) {}

    /**
     * @param  list<array{id: string, title: string}>  $opciones
     * @return array{kind: string, body: array{text: string}, buttons?: array, button?: string, sections?: array}
     */
    public function construir(string $cuerpo, array $opciones, ?string $pie = null): array
    {
        $opciones = array_values(array_filter($opciones, fn ($o) => ($o['id'] ?? '') !== '' && ($o['title'] ?? '') !== ''));

        if (count($opciones) <= 3) {
            $payload = [
                'kind' => 'button',
                'body' => ['text' => mb_substr($cuerpo, 0, 1024)],
                'buttons' => array_map(fn ($o) => [
                    'id' => $o['id'],
                    'title' => mb_substr($o['title'], 0, 20),
                ], $opciones),
            ];
            if ($pie) {
                $payload['footer'] = ['text' => mb_substr($pie, 0, 60)];
            }

            return $payload;
        }

        $rows = [];
        foreach (array_slice($opciones, 0, 10) as $o) {
            $rows[] = [
                'id' => $o['id'],
                'title' => mb_substr($o['title'], 0, 24),
                'description' => mb_substr($o['description'] ?? '', 0, 72),
            ];
        }

        return [
            'kind' => 'list',
            'body' => ['text' => mb_substr($cuerpo, 0, 1024)],
            'button' => 'Ver opciones',
            'sections' => [
                ['title' => 'Opciones', 'rows' => $rows],
            ],
        ];
    }
}
