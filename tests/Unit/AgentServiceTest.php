<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Services\AgentService;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentService $agentService;
    private BotSetting $mockSettings;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear BotSetting con todos los campos necesarios
        $this->mockSettings = BotSetting::create([
            'auto_reply_enabled' => true,
            'reminder_3min_seconds' => 180,
            'reminder_15min_seconds' => 900,
            'reminder_3min_message' => '¿Sigues ahí hermosa? 💕',
            'reminder_15min_message' => '¿Estás ahí? Si necesitas algo, avísame ✨',
            'system_prompt' => 'Sistema de chatbot de ventas',
            'yape_number' => '',
            'yape_holder' => '',
            'welcome_message' => '',
            'escalation_message' => 'Consultando con asesor',
            'groq_api_key' => '',
            'model_chat' => '',
            'model_vision' => '',
        ]);

        // Mockear LlmService para retornar el BotSetting creado
        $mockLlmService = Mockery::mock(LlmService::class);
        $mockLlmService->shouldReceive('getSettings')->andReturn($this->mockSettings);

        $this->agentService = new AgentService($mockLlmService);
    }

    public function test_check_reminders_sends_3min_reminder_for_size_selection_stage()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4), // 4 minutos atrás
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(1, $reminders);
        $this->assertEquals('3min', $reminders[0]['type']);
        $this->assertEquals('51912345678', $reminders[0]['phone_number']);

        $state->refresh();
        $this->assertEquals('3min', $state->last_reminder_sent);
    }

    public function test_check_reminders_sends_3min_reminder_for_color_selection_stage()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_color_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(1, $reminders);
        $this->assertEquals('3min', $reminders[0]['type']);
    }

    public function test_check_reminders_sends_3min_reminder_for_shipping_data_stage()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_shipping_data',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(1, $reminders);
        $this->assertEquals('3min', $reminders[0]['type']);
    }

    public function test_check_reminders_sends_3min_reminder_for_card_full_name_stage()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_card_full_name',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(1, $reminders);
        $this->assertEquals('3min', $reminders[0]['type']);
    }

    public function test_check_reminders_does_not_send_reminder_when_auto_reply_disabled()
    {
        // Actualizar el BotSetting existente
        $this->mockSettings->update(['auto_reply_enabled' => false]);
        
        // Crear un nuevo mock con el setting actualizado
        $mockLlmService = Mockery::mock(LlmService::class);
        $mockLlmService->shouldReceive('getSettings')->andReturn($this->mockSettings->fresh());
        
        $this->agentService = new AgentService($mockLlmService);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(0, $reminders);
    }

    public function test_check_reminders_does_not_send_when_requires_human_true()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => true,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(0, $reminders);
    }

    public function test_check_reminders_does_not_send_for_payment_validation_stage()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => 'none',
            'context' => [
                'sales_stage' => 'awaiting_payment_validation',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(0, $reminders);
    }

    public function test_check_reminders_sends_15min_reminder_after_3min()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(16), // 16 minutos atrás
            'last_reminder_sent' => '3min',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(1, $reminders);
        $this->assertEquals('15min', $reminders[0]['type']);

        $state->refresh();
        $this->assertEquals('15min', $state->last_reminder_sent);
    }

    public function test_check_reminders_does_not_send_3min_if_already_sent()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(4),
            'last_reminder_sent' => '3min',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(0, $reminders);
    }

    public function test_check_reminders_does_not_send_15min_if_already_sent()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'requires_human' => false,
            'last_activity_at' => now()->subMinutes(20),
            'last_reminder_sent' => '15min',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
            ],
        ]);

        $reminders = $this->agentService->checkReminders();

        $this->assertCount(0, $reminders);
    }
}