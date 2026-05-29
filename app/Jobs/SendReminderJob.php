<?php

namespace App\Jobs;

use App\Infrastructure\Whatsapp\RomaWhatsappClient;
use App\Models\Customer;
use App\Models\ConversationState;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendReminderJob implements ShouldQueue
{
    use Queueable;

    protected string $phoneNumber;
    protected string $message;

    public function __construct(string $phoneNumber, string $message)
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
    }

    public function handle(RomaWhatsappClient $client): void
    {
        $waId = 'reminder_' . uniqid();

        Log::info('SendReminderJob: Sending reminder message', [
            'phone' => $this->phoneNumber,
            'waId' => $waId,
        ]);

        try {
            $response = $client->sendMessage($this->phoneNumber, $this->message, $waId);

            // 1. Obtener o crear Cliente
            $customer = Customer::firstOrCreate(
                ['phone_number' => $this->phoneNumber],
                [
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'segment' => 'lead',
                ]
            );

            // 2. Obtener o crear ConversationState
            $conversationState = ConversationState::firstOrCreate(
                ['phone_number' => $this->phoneNumber],
                [
                    'customer_id' => $customer->id,
                    'current_state' => 'greeting',
                    'context' => [],
                    'last_activity_at' => now(),
                ]
            );

            if (empty($conversationState->customer_id)) {
                $conversationState->update(['customer_id' => $customer->id]);
            }

            // 3. Registrar el mensaje en la base de datos
            $msg = Message::create([
                'message_id' => $response['message_id'] ?? $waId,
                'phone_number' => $this->phoneNumber,
                'customer_id' => $customer->id,
                'conversation_state_id' => $conversationState->id,
                'customer_name' => $customer->name,
                'content' => $this->message,
                'direction' => 'outgoing',
                'status' => 'sent',
                'whatsapp_timestamp' => now(),
                'metadata' => [
                    'is_reminder' => true,
                    'roma_api_response' => $response,
                ],
            ]);

            // 4. Disparar Pusher
            if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                try {
                    broadcast(new \App\Events\MessageReceived($msg))->toOthers();
                } catch (\Exception $e) {
                    Log::error('SendReminderJob: Error broadcasting reminder message: ' . $e->getMessage());
                }
            }

            Log::info('SendReminderJob: Sent successfully', ['id' => $msg->id]);

        } catch (\Exception $e) {
            Log::error('SendReminderJob failed: ' . $e->getMessage());
            
            // Guardar como fallido en la base de datos
            try {
                $customer = Customer::where('phone_number', $this->phoneNumber)->first();
                $conversationState = ConversationState::where('phone_number', $this->phoneNumber)->first();

                $msg = Message::create([
                    'message_id' => $waId,
                    'phone_number' => $this->phoneNumber,
                    'customer_id' => $customer?->id,
                    'conversation_state_id' => $conversationState?->id,
                    'customer_name' => $customer?->name,
                    'content' => $this->message,
                    'direction' => 'outgoing',
                    'status' => 'failed',
                    'whatsapp_timestamp' => now(),
                    'metadata' => [
                        'is_reminder' => true,
                        'send_error' => $e->getMessage(),
                    ],
                ]);

                if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                    try {
                        broadcast(new \App\Events\MessageReceived($msg))->toOthers();
                    } catch (\Exception $broadcastException) {
                        Log::error('SendReminderJob: Error broadcasting failed reminder state: ' . $broadcastException->getMessage());
                    }
                }
            } catch (\Exception $dbEx) {
                Log::error('SendReminderJob: Failed to save failed reminder status: ' . $dbEx->getMessage());
            }
        }
    }
}
