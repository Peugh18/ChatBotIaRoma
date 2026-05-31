<?php

namespace Tests\Unit;

use App\Models\ConversationState;
use App\Services\BusinessConfigService;
use App\Services\SalesFlowService;
use App\Services\SalesNudgeService;
use App\Services\ToolExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SalesNudgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_handles_price_objection_at_confirmation(): void
    {
        $state = ConversationState::create([
            'phone_number' => '51990001111',
            'current_state' => 'greeting',
            'context' => [
                'sales_stage' => 'awaiting_order_confirmation',
                'current_product_name' => 'Aurora',
            ],
        ]);

        $tools = Mockery::mock(ToolExecutorService::class);
        $tools->shouldReceive('executeSendInteractiveButtons')->once();

        $service = new SalesNudgeService(
            app(BusinessConfigService::class),
            $tools,
            app(SalesFlowService::class)
        );

        $result = $service->tryRespond($state, 'está muy caro', false);

        $this->assertNotNull($result);
        $this->assertStringContainsString('entiendo', mb_strtolower($result['text']));
    }

    public function test_buy_intent_during_size_selection_prompts_for_size(): void
    {
        $state = ConversationState::create([
            'phone_number' => '51990002222',
            'current_state' => 'greeting',
            'context' => [
                'sales_stage' => 'awaiting_size_selection',
                'available_sizes' => ['M', 'L'],
            ],
        ]);

        $service = new SalesNudgeService(
            app(BusinessConfigService::class),
            Mockery::mock(ToolExecutorService::class),
            app(SalesFlowService::class)
        );

        $result = $service->tryRespond($state, 'lo quiero ya', false);

        $this->assertNotNull($result);
        $this->assertStringContainsString('M', $result['text']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
