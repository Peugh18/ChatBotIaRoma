<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HumanEscalation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $phoneNumber;
    public $customerName;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
        $this->phoneNumber = $message->phone_number;
        $this->customerName = $message->customer_name;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('crm.escalations'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'human.escalation';
    }

    public function broadcastWith(): array
    {
        $handoff = [];
        $state = $this->message->conversationState;
        if ($state) {
            $handoff = $state->context['handoff'] ?? [];
        }

        return [
            'phone_number' => $this->phoneNumber,
            'customer_name' => $this->customerName,
            'message_id' => $this->message->id,
            'content' => $this->message->content,
            'timestamp' => now()->toIso8601String(),
            'handoff' => $handoff,
            'requires_attention' => true,
        ];
    }
}
