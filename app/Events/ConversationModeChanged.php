<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationModeChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $phoneNumber,
        public string $mode,
        public bool $isAutoEscalated = false,
        public bool $asesorPostPedido = false,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('crm.messages'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.mode';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'phone_number' => $this->phoneNumber,
            'mode' => $this->mode,
            'requires_human' => $this->mode === 'human',
            'is_auto_escalated' => $this->isAutoEscalated,
            'asesor_post_pedido' => $this->asesorPostPedido,
        ];
    }
}
