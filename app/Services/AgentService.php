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

        $states = ConversationState::where('requires_human', false)
            ->where('last_activity_at', '>', now()->subHours(1))
            ->get();

        foreach ($states as $state) {
            $stage = $state->context['sales_stage'] ?? null;
            if (!in_array($stage, [
                'awaiting_order_confirmation',
                'awaiting_payment_method',
                'awaiting_payment_proof',
            ], true)) {
                continue;
            }

            $elapsed = now()->diffInSeconds($state->last_activity_at);

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
