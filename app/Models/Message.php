<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'message_id',
        'phone_number',
        'customer_id',
        'conversation_state_id',
        'customer_name',
        'content',
        'direction',
        'status',
        'whatsapp_timestamp',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'whatsapp_timestamp' => 'datetime',
    ];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversationState(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ConversationState::class);
    }
}
