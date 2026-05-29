<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Services\DeterministicBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterministicBotTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_response_when_auto_reply_is_disabled(): void
    {
        BotSetting::create([
            'auto_reply_enabled' => false,
            'system_prompt' => 'test',
        ]);

        $result = app(DeterministicBotService::class)->process('51977776666', 'hola');

        $this->assertSame('', $result['text']);
        $this->assertSame([], $result['metadata']);
    }

    public function test_skips_processing_when_conversation_requires_human(): void
    {
        BotSetting::create([
            'auto_reply_enabled' => true,
            'system_prompt' => 'test',
        ]);

        ConversationState::create([
            'phone_number' => '51977776666',
            'current_state' => 'greeting',
            'requires_human' => true,
            'context' => [],
        ]);

        $result = app(DeterministicBotService::class)->process('51977776666', 'hola');

        $this->assertSame('', $result['text']);
    }
}
