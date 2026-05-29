<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        $customers = Customer::query()
            ->with(['conversationState:id,customer_id,phone_number,requires_human,current_state'])
            ->withCount('orders')
            ->withSum(
                ['orders as total_spent' => fn ($query) => $query->where('status', '!=', 'cancelled')],
                'amount_total'
            )
            ->orderByDesc('last_seen_at')
            ->get();

        return response()->json($customers);
    }

    public function show(string $id): JsonResponse
    {
        $customer = Customer::with(['conversationState', 'messages', 'orders.items.product'])
            ->findOrFail($id);

        return response()->json($customer);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'segment' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'requires_human' => 'nullable|boolean',
        ]);

        // Separar requires_human para actualizar en el estado
        $customerData = $validated;
        unset($customerData['requires_human']);
        $customer->update($customerData);

        if (isset($validated['requires_human'])) {
            $customer->conversationState()->update([
                'requires_human' => $validated['requires_human'],
            ]);
        }

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer->load('conversationState'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
