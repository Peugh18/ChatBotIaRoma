<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'yape_number',
        'yape_name',
        'business_hours',
        'social_networks',
        'address',
        'sales_tone',
        'sales_closing_cta',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'social_networks' => 'array',
    ];
}
