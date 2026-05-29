<?php

namespace App\Jobs;

use App\Models\ConversationState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoReturnToBotJob implements ShouldQueue
{
    use Queueable;

    /**
     * Tiempo en minutos después del cual una conversación en modo humano
     * vuelve automáticamente a modo bot si no hay actividad humana.
     */
    protected int $inactivityThresholdMinutes = 15;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('AutoReturnToBotJob: Checking for inactive human conversations');

        $threshold = now()->subMinutes($this->inactivityThresholdMinutes);

        // Buscar conversaciones en modo humano sin actividad reciente
        $conversationsToReturn = ConversationState::where('requires_human', true)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_human_activity_at')
                      ->orWhere('last_human_activity_at', '<', $threshold);
            })
            ->get();

        $count = 0;
        foreach ($conversationsToReturn as $conversation) {
            $conversation->update([
                'requires_human' => false,
                'is_auto_escalated' => false,
                'last_human_activity_at' => null,
            ]);

            Log::info('AutoReturnToBotJob: Returned conversation to bot mode', [
                'phone' => $conversation->phone_number,
                'customer_id' => $conversation->customer_id,
            ]);

            $count++;
        }

        Log::info('AutoReturnToBotJob: Completed', [
            'checked' => $conversationsToReturn->count(),
            'returned_to_bot' => $count,
        ]);
    }
}
