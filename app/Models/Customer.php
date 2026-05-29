<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $fillable = [
        'phone_number',
        'name',
        'email',
        'segment',
        'lifetime_value',
        'first_seen_at',
        'last_seen_at',
        'tags',
        'notes',
    ];

    protected $casts = [
        'tags' => 'array',
        'lifetime_value' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function conversationState(): HasOne
    {
        return $this->hasOne(ConversationState::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
