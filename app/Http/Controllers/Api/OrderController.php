<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Order;
use App\Services\ServicioModoConversacionPedido;
use App\Models\OrderItem;
use App\Support\EtapaVenta;
use App\Ventas\Servicios\ServicioColaValidacionPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with(['customer', 'items.product'])
            ->findOrFail($id);

        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'status' => 'required|string|in:pending,paid,shipped,delivered,cancelled',
            'shipping_method' => 'required|string|in:shalom,motorizado,none',
            'shipping_cost' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:yape,card,link,cash',
            'district' => 'nullable|string',
            'full_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.color' => 'nullable|string',
            'items.*.size' => 'nullable|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += $item['unit_price'] * $item['qty'];
        }

        $total = $subtotal + $validated['shipping_cost'];

        $order = Order::create([
            'customer_id' => $validated['customer_id'],
            'status' => $validated['status'],
            'shipping_method' => $validated['shipping_method'],
            'shipping_cost' => $validated['shipping_cost'],
            'payment_method' => $validated['payment_method'],
            'district' => $validated['district'],
            'full_address' => $validated['full_address'],
            'amount_subtotal' => $subtotal,
            'amount_total' => $total,
            'notes' => $validated['notes'],
        ]);

        foreach ($validated['items'] as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'color' => $item['color'] ?? null,
                'size' => $item['size'] ?? null,
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'total' => $item['unit_price'] * $item['qty'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order->load('items.product'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,paid,shipped,delivered,cancelled',
            'shipping_method' => 'nullable|string|in:shalom,motorizado,none',
            'shipping_cost' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:yape,card,link,cash',
            'district' => 'nullable|string',
            'full_address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $order->update($validated);

        // Actualizar el total si cambia costo de envío
        if (isset($validated['shipping_cost'])) {
            $total = $order->amount_subtotal + $order->shipping_cost;
            $order->update(['amount_total' => $total]);
        }

        // Registrar timestamp especial si cambia estado
        if (isset($validated['status'])) {
            if ($validated['status'] === 'paid') {
                $order->update(['paid_at' => now()]);
            } elseif ($validated['status'] === 'shipped') {
                $order->update(['shipped_at' => now()]);
            } elseif ($validated['status'] === 'delivered') {
                $order->update(['delivered_at' => now()]);
            }
        }

        $order->refresh();
        $paymentValidationReady = false;
        if (($validated['status'] ?? null) === 'paid' && $order->conversation_state_id) {
            $state = ConversationState::find($order->conversation_state_id);
            if ($state && EtapaVenta::esValidacionPago($state)) {
                $paymentValidationReady = ! $state->requires_human;
            }
        }

        if (($validated['status'] ?? null) === 'delivered' && $order->conversation_state_id) {
            $state = ConversationState::find($order->conversation_state_id);
            if ($state) {
                app(ServicioModoConversacionPedido::class)->reactivarBotTrasEntrega($state);
            }
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => $order,
            'payment_validation_ready' => $paymentValidationReady,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }

    public function getStats(): JsonResponse
    {
        // 1. Total Ventas (excluyendo canceladas)
        $totalSales = Order::where('status', '!=', 'cancelled')->sum('amount_total');

        // 2. Ticket Promedio (órdenes pagadas/entregadas/enviadas)
        $validOrdersQuery = Order::whereIn('status', ['paid', 'shipped', 'delivered']);
        $validOrdersCount = $validOrdersQuery->count();
        $averageTicket = $validOrdersCount > 0 ? $validOrdersQuery->sum('amount_total') / $validOrdersCount : 0;

        // 3. Conversaciones que requieren asesor humano
        $openConversations = ConversationState::where('requires_human', true)->count();

        $paymentValidationQueue = app(ServicioColaValidacionPago::class)->pendientes(10);

        // 4. Clientes totales
        $totalCustomers = Customer::count();

        // 5. Tasa de Conversión (clientes con órdenes de compra vs total clientes)
        $convertedCustomers = Customer::has('orders')->count();
        $conversionRate = $totalCustomers > 0 ? ($convertedCustomers / $totalCustomers) * 100 : 0;

        // 6. Órdenes recientes
        $recentOrders = Order::with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'total_sales' => round($totalSales, 2),
            'average_ticket' => round($averageTicket, 2),
            'open_conversations' => $openConversations,
            'total_customers' => $totalCustomers,
            'conversion_rate' => round($conversionRate, 1),
            'recent_orders' => $recentOrders,
            'payment_validation_count' => $paymentValidationQueue->count(),
            'payment_validation_queue' => $paymentValidationQueue,
        ]);
    }

    public function paymentValidationQueue(ServicioColaValidacionPago $cola): JsonResponse
    {
        return response()->json([
            'items' => $cola->pendientes(30),
            'count' => $cola->contar(),
        ]);
    }
}
