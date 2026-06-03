<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSetting extends Model
{
    protected $fillable = [
        'system_prompt',
        'yape_number',
        'yape_holder',
        'welcome_message',
        'mensaje_presentacion',
        'reminder_3min_message',
        'reminder_15min_message',
        'escalation_message',
        'auto_reply_enabled',
        'groq_api_key',
        'model_chat',
        'model_vision',
        'reminder_3min_seconds',
        'reminder_15min_seconds',
        'voyage_api_key',
    ];

    protected $casts = [
        'auto_reply_enabled' => 'boolean',
    ];
}
