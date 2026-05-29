<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationState extends Model
{
    protected $fillable = [
        'phone_number',
        'customer_id',
        'current_state',
        'context',
        'last_activity_at',
        'requires_human',
        'last_reminder_sent',
        'assigned_to',
        'last_human_activity_at',
        'is_auto_escalated',
    ];

    protected $casts = [
        'context' => 'array',
        'last_activity_at' => 'datetime',
        'last_human_activity_at' => 'datetime',
        'requires_human' => 'boolean',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function handoffs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AgentHandoff::class);
    }
}
