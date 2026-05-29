<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentHandoff extends Model
{
    protected $fillable = [
        'conversation_state_id',
        'reason',
        'requested_at',
        'taken_by_user_id',
        'taken_at',
        'returned_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'taken_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function conversationState(): BelongsTo
    {
        return $this->belongsTo(ConversationState::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by_user_id');
    }
}
