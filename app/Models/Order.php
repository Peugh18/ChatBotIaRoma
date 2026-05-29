<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'conversation_state_id',
        'status',
        'shipping_method',
        'shipping_cost',
        'payment_method',
        'payment_proof_url',
        'district',
        'full_address',
        'location',
        'amount_subtotal',
        'amount_total',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'location' => 'array',
        'shipping_cost' => 'decimal:2',
        'amount_subtotal' => 'decimal:2',
        'amount_total' => 'decimal:2',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversationState(): BelongsTo
    {
        return $this->belongsTo(ConversationState::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
