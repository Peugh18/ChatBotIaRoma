<?php

namespace Tests\Unit;

use App\Support\RomaWebhookAuth;
use Illuminate\Http\Request;
use Tests\TestCase;

class RomaWebhookAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.roma.token' => 'test-token',
            'services.roma.webhook_secret' => '',
        ]);
    }

    public function test_rejects_requests_without_valid_token(): void
    {
        $request = Request::create('/api/roma/webhook', 'POST', [], [], [], [], '{}');

        $this->assertFalse(RomaWebhookAuth::verify($request));
    }

    public function test_accepts_valid_bearer_token(): void
    {
        $request = Request::create('/api/roma/webhook', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ], '{}');

        $this->assertTrue(RomaWebhookAuth::verify($request));
    }

    public function test_verifies_hmac_signature_when_webhook_secret_is_set(): void
    {
        config(['services.roma.webhook_secret' => 'secret-key']);

        $body = '{"event":"test"}';
        $signature = hash_hmac('sha256', $body, 'secret-key');

        $request = Request::create('/api/roma/webhook', 'POST', [], [], [], [
            'HTTP_X_ROMA_SYNC_TOKEN' => 'test-token',
            'HTTP_X_ROMA_SIGNATURE' => 'sha256=' . $signature,
        ], $body);

        $this->assertTrue(RomaWebhookAuth::verify($request));
    }
}
