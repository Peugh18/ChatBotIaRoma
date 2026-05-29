<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Models\CompanySetting;
use App\Models\ConversationState;
use App\Services\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_sales_voice_and_xml_sections_in_system_prompt(): void
    {
        CompanySetting::create([
            'company_name' => 'Vestidos Roma',
            'yape_number' => '999888777',
            'yape_name' => 'Vestidos Roma SAC',
            'address' => 'Av. Principal 123',
            'business_hours' => ['lun-vie' => '10:00-20:00'],
            'sales_tone' => 'elegante y cercano',
            'sales_closing_cta' => '¿Lo reservamos?',
        ]);

        BotSetting::create([
            'system_prompt' => 'Eres Roma, asesora experta.',
            'auto_reply_enabled' => true,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51988887777',
            'current_state' => 'greeting',
            'context' => [
                'current_product_name' => 'Vestido Luna',
                'last_shown_products' => [
                    ['id' => 5, 'name' => 'Vestido Luna', 'final_price' => 199, 'has_stock' => true],
                ],
            ],
        ]);

        $prompt = app(PromptBuilder::class)->buildSystemPrompt($state, [
            'name' => 'Ana',
            'total_spent' => 450,
        ]);

        $this->assertStringContainsString('<empresa>', $prompt);
        $this->assertStringContainsString('elegante y cercano', $prompt);
        $this->assertStringContainsString('¿Lo reservamos?', $prompt);
        $this->assertStringContainsString('<cliente>', $prompt);
        $this->assertStringContainsString('Ana', $prompt);
        $this->assertStringContainsString('<contrato>', $prompt);
        $this->assertStringContainsString('TOOL-FIRST', $prompt);
        $this->assertStringContainsString('Vestido Luna', $prompt);
    }

    public function test_formats_business_hours_as_readable_text(): void
    {
        $service = app(\App\Services\BusinessConfigService::class);

        $formatted = $service->formatBusinessHours([
            'lun-vie' => ['open' => '10:00', 'close' => '20:00'],
            'sáb' => '10:00-14:00',
        ]);

        $this->assertStringContainsString('lun-vie', $formatted);
        $this->assertStringContainsString('10:00', $formatted);
    }
}
