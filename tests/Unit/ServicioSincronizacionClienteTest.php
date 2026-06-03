<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\ConversationState;
use App\Services\ServicioSincronizacionCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicioSincronizacionClienteTest extends TestCase
{
    use RefreshDatabase;

    private ServicioSincronizacionCliente $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = app(ServicioSincronizacionCliente::class);
    }

    public function test_sync_card_full_name_to_customer_name_when_empty()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => null,
            'email' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'card_full_name' => 'Maria Garcia',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('Maria Garcia', $customer->name);
    }

    public function test_does_not_overwrite_existing_customer_name()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => 'Existing Name',
            'email' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'card_full_name' => 'Maria Garcia',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('Existing Name', $customer->name);
    }

    public function test_sync_card_email_to_customer_email_when_empty()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => null,
            'email' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'card_email' => 'maria@example.com',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('maria@example.com', $customer->email);
    }

    public function test_sync_ship_full_name_to_customer_name_when_empty()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => null,
            'email' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'ship_full_name' => 'Juan Perez',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('Juan Perez', $customer->name);
    }

    public function test_sync_card_phone_to_alternate_phone_when_empty()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => null,
            'email' => null,
            'alternate_phone' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'card_phone' => '51987654321',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('51987654321', $customer->alternate_phone);
    }

    public function test_sync_ship_phone_to_alternate_phone_when_empty()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => null,
            'email' => null,
            'alternate_phone' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'ship_phone' => '51987654321',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('51987654321', $customer->alternate_phone);
    }

    public function test_sync_multiple_fields_in_single_call()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => null,
            'email' => null,
            'alternate_phone' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'card_full_name' => 'Maria Garcia',
                'card_email' => 'maria@example.com',
                'card_phone' => '51987654321',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('Maria Garcia', $customer->name);
        $this->assertEquals('maria@example.com', $customer->email);
        $this->assertEquals('51987654321', $customer->alternate_phone);
    }

    public function test_does_not_sync_when_no_customer_exists()
    {
        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => null,
            'context' => [
                'card_full_name' => 'Maria Garcia',
                'card_email' => 'maria@example.com',
            ],
        ]);

        // Should not throw error
        $this->syncService->syncFromConversationContext($state);

        $this->assertTrue(true);
    }

    public function test_ship_full_name_syncs_only_if_name_is_still_empty()
    {
        $customer = Customer::create([
            'phone_number' => '51912345678',
            'name' => 'Already Set',
            'email' => null,
        ]);

        $state = ConversationState::create([
            'phone_number' => '51912345678',
            'customer_id' => $customer->id,
            'context' => [
                'ship_full_name' => 'Juan Perez',
            ],
        ]);

        $this->syncService->syncFromConversationContext($state);

        $customer->refresh();
        $this->assertEquals('Already Set', $customer->name);
    }
}