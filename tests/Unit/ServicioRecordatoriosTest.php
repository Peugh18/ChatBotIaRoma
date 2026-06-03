<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Services\ServicioRecordatorios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicioRecordatoriosTest extends TestCase
{
    use RefreshDatabase;

    public function test_recordatorios_desactivados_durante_reconstruccion(): void
    {
        BotSetting::create([
            'auto_reply_enabled' => true,
            'reminder_3min_seconds' => 180,
            'reminder_15min_seconds' => 900,
            'reminder_3min_message' => '¿Sigues ahí?',
            'reminder_15min_message' => '¿Estás ahí?',
            'system_prompt' => '',
            'yape_number' => '',
            'yape_holder' => '',
            'welcome_message' => '',
            'escalation_message' => '',
            'groq_api_key' => '',
            'model_chat' => '',
            'model_vision' => '',
        ]);

        $this->assertSame([], app(ServicioRecordatorios::class)->checkReminders());
    }
}
