<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\CompanySetting;

/**
 * Fuente única de configuración de negocio (marca, pagos, tono).
 * BotSetting solo cubre motor IA (modelos, prompts, recordatorios).
 */
class BusinessConfigService
{
    public function company(): ?CompanySetting
    {
        return CompanySetting::first();
    }

    public function bot(): ?BotSetting
    {
        return BotSetting::first();
    }

    public function welcomeMessage(): string
    {
        $custom = trim((string) ($this->bot()?->welcome_message ?? ''));

        return $custom !== ''
            ? $custom
            : '¡Hola! Soy el asistente de Roma. ¿Qué vestido estás buscando hoy? Puedes enviarme una foto o el nombre del vestido.';
    }

    public function yapeNumber(): string
    {
        return $this->company()?->yape_number ?: '912874650';
    }

    public function yapeHolder(): string
    {
        return $this->company()?->yape_name ?: 'Solange Llantoy';
    }

    public function salesTone(): string
    {
        return $this->company()?->sales_tone ?: 'cálido y cercano';
    }

    public function salesClosingCta(): string
    {
        return $this->company()?->sales_closing_cta ?: '¿Te lo separo ahora?';
    }

    public function orderConfirmationPrompt(): string
    {
        $cta = trim($this->salesClosingCta());

        return $cta !== ''
            ? "¿Confirmamos tu pedido? {$cta} 💕"
            : '¿Confirmamos tu pedido hermosa? 💕';
    }

    public function formatYapeNumber(): string
    {
        $raw = preg_replace('/\D/', '', $this->yapeNumber());
        if (strlen($raw) === 9) {
            return substr($raw, 0, 3).' '.substr($raw, 3, 3).' '.substr($raw, 6, 3);
        }

        return $this->yapeNumber();
    }

    public function yapePaymentMessage(): string
    {
        $num = $this->formatYapeNumber();
        $name = $this->yapeHolder();

        return "El yape es a este número 💕\n{$num}\n\nA nombre de:\n{$name}\n\nEnvíanos la captura cuando realices el pago ✨";
    }

    public function paymentInstructions(): string
    {
        return $this->yapePaymentMessage();
    }

    public function applyBrandCta(string $base, bool $withClosing = false): string
    {
        if (! $withClosing) {
            return $base;
        }

        $cta = $this->salesClosingCta();
        if ($cta && ! str_contains(mb_strtolower($base), mb_strtolower($cta))) {
            return $base."\n".$cta;
        }

        return $base;
    }

    /**
     * Bloque de empresa para el system prompt del LLM.
     */
    public function formatCompanyPromptBlock(): string
    {
        $company = $this->company();
        if (! $company) {
            return '';
        }

        $hours = $this->formatBusinessHours($company->business_hours);

        return implode("\n", array_filter([
            "- Tienda: {$company->company_name}",
            $company->address ? "- Dirección: {$company->address}" : null,
            $hours ? "- Horarios: {$hours}" : null,
            '- Tono: '.$this->salesTone(),
            '- CTA de cierre: '.$this->salesClosingCta(),
        ]));
    }

    public function formatBusinessHours(mixed $hours): string
    {
        if (empty($hours)) {
            return '';
        }

        if (is_string($hours)) {
            return $hours;
        }

        if (! is_array($hours)) {
            return '';
        }

        $parts = [];
        foreach ($hours as $day => $slot) {
            if (is_array($slot)) {
                $open = $slot['open'] ?? $slot['from'] ?? '';
                $close = $slot['close'] ?? $slot['to'] ?? '';
                $parts[] = trim("{$day}: {$open}-{$close}", ': -');
            } else {
                $parts[] = "{$day}: {$slot}";
            }
        }

        return implode('; ', $parts);
    }
}
