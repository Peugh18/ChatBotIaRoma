<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\ServicioValidacionPrecios;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard de Health Check para monitoreo del sistema
 */
class HealthCheckController extends Controller
{
    /**
     * Dashboard de salud del sistema
     */
    public function dashboard(): JsonResponse
    {
        // Auditoría de precios
        $priceAudit = ServicioValidacionPrecios::auditAllProducts();

        // Métricas de órdenes
        $zeroAmountOrders = Order::where('amount_total', '<=', 0)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $pendingOrders = Order::where('status', 'pending')->count();
        $deliveredOrders = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Productos sin stock (sin variantes)
        $productsWithoutVariants = Product::doesntHave('variants')->count();

        return response()->json([
            'status' => $priceAudit['healthy'] ? 'healthy' : 'warning',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'prices' => [
                    'status' => $priceAudit['healthy'] ? 'ok' : 'error',
                    'total_products' => $priceAudit['total_products'],
                    'valid' => $priceAudit['valid_count'],
                    'invalid' => $priceAudit['invalid_count'],
                    'invalid_details' => $priceAudit['invalid_products'],
                ],
                'orders' => [
                    'zero_amount_last_7d' => $zeroAmountOrders,
                    'pending' => $pendingOrders,
                    'delivered_last_7d' => $deliveredOrders,
                    'status' => $zeroAmountOrders > 0 ? 'error' : 'ok',
                ],
                'inventory' => [
                    'products_without_variants' => $productsWithoutVariants,
                    'status' => $productsWithoutVariants > 0 ? 'warning' : 'ok',
                ],
            ],
            'summary' => [
                'critical_issues' => ($priceAudit['invalid_count'] > 0 ? 1 : 0) + ($zeroAmountOrders > 0 ? 1 : 0),
                'warnings' => ($productsWithoutVariants > 0 ? 1 : 0),
                'action_required' => !$priceAudit['healthy'] || $zeroAmountOrders > 0,
            ],
        ]);
    }

    /**
     * Check rápido para monitoreo
     */
    public function ping(): JsonResponse
    {
        $audit = ServicioValidacionPrecios::auditAllProducts();

        return response()->json([
            'status' => $audit['healthy'] ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
