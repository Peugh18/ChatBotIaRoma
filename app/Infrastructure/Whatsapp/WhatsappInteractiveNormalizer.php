<?php

namespace App\Infrastructure\Whatsapp;

/**
 * Convierte el formato interno del CRM (kind/button/sections) al formato Meta Cloud API.
 */
class WhatsappInteractiveNormalizer
{
    /**
     * @param array<string, mixed> $internal
     * @return array<string, mixed>|null
     */
    public function toMetaPayload(array $internal): ?array
    {
        $kind = $internal['kind'] ?? $internal['type'] ?? null;

        if ($kind === 'button') {
            return $this->normalizeButtons($internal);
        }

        if ($kind === 'list') {
            return $this->normalizeList($internal);
        }

        // Ya viene en formato Meta (type + action)
        if (isset($internal['type'], $internal['action'])) {
            return $internal;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $internal
     */
    protected function normalizeButtons(array $internal): array
    {
        $bodyText = $internal['body']['text'] ?? $internal['body'] ?? '';
        $buttons = $internal['buttons'] ?? [];

        $metaButtons = [];
        foreach (array_slice($buttons, 0, 3) as $btn) {
            $id = (string) ($btn['id'] ?? '');
            $title = (string) ($btn['title'] ?? '');
            if ($id === '' || $title === '') {
                continue;
            }
            $metaButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => mb_substr($title, 0, 20),
                ],
            ];
        }

        $payload = [
            'type' => 'button',
            'body' => ['text' => mb_substr((string) $bodyText, 0, 1024)],
            'action' => ['buttons' => $metaButtons],
        ];

        if (!empty($internal['footer']['text'])) {
            $payload['footer'] = ['text' => mb_substr((string) $internal['footer']['text'], 0, 60)];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $internal
     */
    protected function normalizeList(array $internal): array
    {
        $bodyText = $internal['body']['text'] ?? $internal['body'] ?? '';
        $buttonLabel = (string) ($internal['button'] ?? 'Ver opciones');
        $sections = $internal['sections'] ?? [];

        $metaSections = [];
        foreach ($sections as $section) {
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                $id = (string) ($row['id'] ?? '');
                $title = (string) ($row['title'] ?? '');
                if ($id === '' || $title === '') {
                    continue;
                }
                $entry = [
                    'id' => $id,
                    'title' => mb_substr($title, 0, 24),
                ];
                if (!empty($row['description'])) {
                    $entry['description'] = mb_substr((string) $row['description'], 0, 72);
                }
                $rows[] = $entry;
            }
            if (empty($rows)) {
                continue;
            }
            $metaSections[] = [
                'title' => mb_substr((string) ($section['title'] ?? 'Opciones'), 0, 24),
                'rows' => array_slice($rows, 0, 10),
            ];
        }

        $payload = [
            'type' => 'list',
            'body' => ['text' => mb_substr((string) $bodyText, 0, 1024)],
            'action' => [
                'button' => mb_substr($buttonLabel, 0, 20),
                'sections' => $metaSections,
            ],
        ];

        if (!empty($internal['footer']['text'])) {
            $payload['footer'] = ['text' => mb_substr((string) $internal['footer']['text'], 0, 60)];
        }

        return $payload;
    }
}
