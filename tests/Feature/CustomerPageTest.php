<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_customers_page(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_customers_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/customers')
            ->assertOk();
    }

    public function test_authenticated_users_can_list_customers_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJson([]);
    }
}
