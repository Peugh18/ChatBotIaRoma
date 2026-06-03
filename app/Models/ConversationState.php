<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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

    protected $appends = [
        'asesor_post_pedido',
    ];

    protected $casts = [
        'context' => 'array',
        'last_activity_at' => 'datetime',
        'last_human_activity_at' => 'datetime',
        'requires_human' => 'boolean',
        'is_auto_escalated' => 'boolean',
    ];

    public function getAsesorPostPedidoAttribute(): bool
    {
        if ((bool) (($this->context ?? [])['asesor_post_pedido'] ?? false)) {
            return true;
        }

        if (! $this->requires_human) {
            return false;
        }

        $orderId = (int) (($this->context ?? [])['ultimo_pedido_id'] ?? ($this->context ?? [])['last_order_id'] ?? 0);
        if ($orderId <= 0) {
            return false;
        }

        $status = Cache::remember(
            'conv_post_pedido_order_'.$orderId,
            30,
            fn () => Order::query()->whereKey($orderId)->value('status'),
        );

        return in_array($status, ['paid', 'shipped'], true);
    }

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
