<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Services\ServicioBotEntrada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotTest extends TestCase
{
    use RefreshDatabase;

    public function test_servicio_bot_entrada_responde_mensaje_reconstruccion(): void
    {
        BotSetting::create([
            'auto_reply_enabled' => true,
            'welcome_message' => '',
            'escalation_message' => 'Asesor',
        ]);

        $resultado = app(ServicioBotEntrada::class)->procesar('51922222222', 'hola');

        $this->assertNotEmpty($resultado['text']);
        $this->assertStringContainsString('mejorando', mb_strtolower($resultado['text']));

        $this->assertNotNull(ConversationState::where('phone_number', '51922222222')->first());
    }

    public function test_servicio_bot_entrada_no_responde_si_bot_desactivado(): void
    {
        BotSetting::create([
            'auto_reply_enabled' => false,
            'welcome_message' => '',
            'escalation_message' => 'Asesor',
        ]);

        $resultado = app(ServicioBotEntrada::class)->procesar('51933333333', 'hola');

        $this->assertSame('', $resultado['text']);
    }

    public function test_webhook_status_update_no_convierte_outgoing_a_incoming(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        config(['services.roma.token' => 'roma_sync_secret_2026']);

        $customer = \App\Models\Customer::create([
            'phone_number' => '51959166911',
            'name' => 'Cliente Test',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $state = ConversationState::create([
            'phone_number' => '51959166911',
            'customer_id' => $customer->id,
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $message = \App\Models\Message::create([
            'message_id' => 'wamid.test12345',
            'phone_number' => '51959166911',
            'customer_id' => $customer->id,
            'conversation_state_id' => $state->id,
            'content' => 'Hola cliente, ¿cómo estás?',
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Roma-Sync-Token' => 'roma_sync_secret_2026',
        ])->postJson('/api/roma/messages', [
            'wa_id' => 'wamid.test12345',
            'sender_phone' => '51959166911',
            'message_body' => '[non-text]',
            'direction' => 'inbound',
            'status' => 'delivered',
            'timestamp' => now()->toIso8601String(),
        ]);

        $response->assertStatus(200);

        $messageFresh = $message->fresh();
        $this->assertEquals('outgoing', $messageFresh->direction);
        $this->assertEquals('Hola cliente, ¿cómo estás?', $messageFresh->content);

        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\ProcessIncomingMessageJob::class);
    }
}
