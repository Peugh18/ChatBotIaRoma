<?php

namespace App\Services;

use App\Jobs\SendReminderJob;
use App\Models\ConversationState;

/**
 * Servicio legacy mínimo.
 *
 * Mantiene solo checkReminders para compatibilidad con comandos existentes.
 * El procesamiento principal del bot ahora vive en DeterministicBotService.
 */
class AgentService
{
    public function __construct(
        protected LlmService $llmService
    ) {
    }

    public function getSettings()
    {
        return $this->llmService->getSettings();
    }

    public function checkReminders(): array
    {
        $reminders = [];
        $settings = $this->llmService->getSettings();

        // Respetar BotSetting.auto_reply_enabled
        if (!$settings->auto_reply_enabled) {
            return $reminders;
        }

        $states = ConversationState::where('requires_human', false)
            ->where('last_activity_at', '>', now()->subHours(1))
            ->get();

        // Etapas donde el cliente puede quedarse colgado y necesita reminder
        $eligibleStages = [
            'awaiting_color_selection',
            'awaiting_size_selection',
            'awaiting_order_confirmation',
            'awaiting_shipping_method',
            'awaiting_district',
            'awaiting_shalom_region',
            'awaiting_order_summary',
            'awaiting_payment_method',
            'awaiting_payment_proof',
            'awaiting_shipping_data',
            'awaiting_card_full_name',
            'awaiting_card_email',
            'awaiting_card_phone',
        ];

        foreach ($states as $state) {
            $stage = $state->context['sales_stage'] ?? null;
            
            // No enviar reminder si está en awaiting_payment_validation (requiere validación humana)
            if ($stage === 'awaiting_payment_validation') {
                continue;
            }

            if (!in_array($stage, $eligibleStages, true)) {
                continue;
            }

            $elapsed = $state->last_activity_at->diffInSeconds(now());

            if ($elapsed >= $settings->reminder_3min_seconds && $state->last_reminder_sent === 'none') {
                SendReminderJob::dispatch($state->phone_number, $settings->reminder_3min_message);
                $reminders[] = [
                    'phone_number' => $state->phone_number,
                    'message' => $settings->reminder_3min_message,
                    'type' => '3min',
                ];
                $state->update(['last_reminder_sent' => '3min']);
            } elseif ($elapsed >= $settings->reminder_15min_seconds && $state->last_reminder_sent === '3min') {
                SendReminderJob::dispatch($state->phone_number, $settings->reminder_15min_message);
                $reminders[] = [
                    'phone_number' => $state->phone_number,
                    'message' => $settings->reminder_15min_message,
                    'type' => '15min',
                ];
                $state->update(['last_reminder_sent' => '15min']);
            }
        }

        return $reminders;
    }
}
