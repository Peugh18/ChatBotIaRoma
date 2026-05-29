<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_orders(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'phone_number' => '51911111111',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'shipping_method' => 'none',
            'shipping_cost' => 0,
            'payment_method' => 'yape',
            'amount_subtotal' => 150,
            'amount_total' => 150,
        ]);

        $this->actingAs($user)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'pending');
    }

    public function test_authenticated_user_can_update_order_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'phone_number' => '51922222222',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'shipping_method' => 'shalom',
            'shipping_cost' => 10,
            'payment_method' => 'yape',
            'amount_subtotal' => 200,
            'amount_total' => 210,
        ]);

        $this->actingAs($user)
            ->putJson("/api/orders/{$order->id}", ['status' => 'paid'])
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
        $this->assertNotNull($order->fresh()->paid_at);
    }
}
