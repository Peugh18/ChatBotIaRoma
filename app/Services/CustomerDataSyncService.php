<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ConversationState;
use Illuminate\Support\Facades\Log;

class CustomerDataSyncService
{
    /**
     * Sincroniza datos del cliente desde el contexto de conversación a la tabla customers.
     * Solo llena campos vacíos (fill-if-empty) para no sobrescribir datos existentes.
     */
    public function syncFromConversationContext(ConversationState $state): void
    {
        $ctx = $state->context ?? [];
        $customer = $state->customer;

        if (!$customer) {
            Log::warning('CustomerDataSyncService: No customer found for state', [
                'phone' => $state->phone_number,
            ]);
            return;
        }

        $needsUpdate = false;
        $updates = [];

        // Sincronizar card_full_name → customers.name (si está vacío)
        if (!empty($ctx['card_full_name']) && empty($customer->name)) {
            $updates['name'] = trim($ctx['card_full_name']);
            $needsUpdate = true;
            Log::info('CustomerDataSyncService: Syncing card_full_name to customer.name', [
                'phone' => $state->phone_number,
                'name' => $ctx['card_full_name'],
            ]);
        }

        // Sincronizar card_email → customers.email (si está vacío)
        if (!empty($ctx['card_email']) && empty($customer->email)) {
            $updates['email'] = trim($ctx['card_email']);
            $needsUpdate = true;
            Log::info('CustomerDataSyncService: Syncing card_email to customer.email', [
                'phone' => $state->phone_number,
                'email' => $ctx['card_email'],
            ]);
        }

        // Sincronizar ship_full_name → customers.name (si name aún está vacío)
        if (!empty($ctx['ship_full_name']) && empty($customer->name)) {
            $updates['name'] = trim($ctx['ship_full_name']);
            $needsUpdate = true;
            Log::info('CustomerDataSyncService: Syncing ship_full_name to customer.name', [
                'phone' => $state->phone_number,
                'name' => $ctx['ship_full_name'],
            ]);
        }

        // Sincronizar card_phone → customers.alternate_phone (si está vacío)
        if (!empty($ctx['card_phone']) && empty($customer->alternate_phone)) {
            $updates['alternate_phone'] = trim($ctx['card_phone']);
            $needsUpdate = true;
            Log::info('CustomerDataSyncService: Syncing card_phone to customer.alternate_phone', [
                'phone' => $state->phone_number,
                'alternate_phone' => $ctx['card_phone'],
            ]);
        }

        // Sincronizar ship_phone → customers.alternate_phone (si alternate_phone está vacío)
        if (!empty($ctx['ship_phone']) && empty($customer->alternate_phone)) {
            $updates['alternate_phone'] = trim($ctx['ship_phone']);
            $needsUpdate = true;
            Log::info('CustomerDataSyncService: Syncing ship_phone to customer.alternate_phone', [
                'phone' => $state->phone_number,
                'alternate_phone' => $ctx['ship_phone'],
            ]);
        }

        if ($needsUpdate && !empty($updates)) {
            $customer->update($updates);
            Log::info('CustomerDataSyncService: Customer updated successfully', [
                'phone' => $state->phone_number,
                'updates' => $updates,
            ]);
        }
    }
}