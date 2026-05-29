<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'district',
        'cost_motorizado',
        'cost_shalom',
    ];

    protected $casts = [
        'cost_motorizado' => 'decimal:2',
        'cost_shalom' => 'decimal:2',
    ];
}
