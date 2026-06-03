<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SedeShalom extends Model
{
    protected $table = 'sedes_shalom';

    protected $fillable = [
        'nombre',
        'ciudad',
        'region',
        'costo',
        'activo',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'activo' => 'boolean',
    ];
}
