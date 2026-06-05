<?php

use App\Http\Controllers\Api\BotFlowDebugController;
use App\Http\Controllers\Api\BotMetricsController;
use App\Http\Controllers\Api\BotSettingsController;
use App\Http\Controllers\Api\CatalogVisionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\ConversationSalesContextController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductoSimilarController;
use App\Http\Controllers\Api\ProductVariantPhotoController;
use App\Http\Controllers\Api\RomaSyncController;
use App\Http\Middleware\RateLimitTools;
use Illuminate\Support\Facades\Route;

// Rutas públicas para Roma API (con autenticación por token externa)
Route::prefix('roma')->middleware(RateLimitTools::class)->group(function () {
    Route::post('/sync', [RomaSyncController::class, 'sync']);
    Route::post('/webhook', [RomaSyncController::class, 'webhook']);
    Route::post('/messages', [RomaSyncController::class, 'receiveMessage']);
});

// Endpoint para obtener CSRF token fresco (soluciona error 419)
Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
        'expires' => now()->addHours(2)->toIso8601String(),
    ]);
})->middleware('web');

// Rutas API para el CRM (protegidas por sesión web)
Route::middleware(['web', 'auth'])->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::post('product-variants/{variant}/photo', [ProductVariantPhotoController::class, 'store']);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('delivery-zones', DeliveryZoneController::class);
    Route::get('products/{product}/similares', [ProductoSimilarController::class, 'show']);
    Route::put('products/{product}/similares', [ProductoSimilarController::class, 'update']);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('orders', OrderController::class);
    Route::post('orders/{order}/marcar-enviado', [OrderController::class, 'marcarEnviado']);
    Route::get('dashboard-stats', [OrderController::class, 'getStats']);
    Route::get('payment-validation-queue', [OrderController::class, 'paymentValidationQueue']);
    Route::post('bot-debug/simulate', [BotFlowDebugController::class, 'simulate']);
    Route::post('bot-debug/reset', [BotFlowDebugController::class, 'reset']);
    Route::get('health', [HealthCheckController::class, 'dashboard']);
    Route::get('company-settings', [CompanySettingController::class, 'index']);
    Route::put('company-settings', [CompanySettingController::class, 'update']);
    Route::get('bot-settings', [BotSettingsController::class, 'index']);
    Route::put('bot-settings', [BotSettingsController::class, 'update']);
    Route::post('test-embedding', [CatalogVisionController::class, 'testEmbedding']);
    Route::get('catalog-vision/stats', [CatalogVisionController::class, 'stats']);
    Route::post('catalog-vision/reindex', [CatalogVisionController::class, 'reindex']);
    Route::get('bot-metrics', BotMetricsController::class);
    Route::get('/messages', [RomaSyncController::class, 'getMessages']);
    Route::post('/send-message', [RomaSyncController::class, 'sendMessage']);
    Route::post('/messages/{messageId}/retry', [RomaSyncController::class, 'retryMessage']);
    Route::get('/conversations/{phone}/mode', [RomaSyncController::class, 'getMode']);
    Route::post('/conversations/{phone}/mode', [RomaSyncController::class, 'setMode']);
    Route::get('/conversations/{phone}/sales-context', [ConversationSalesContextController::class, 'show']);
    Route::post('/conversations/{phone}/validate-payment', [RomaSyncController::class, 'validatePayment']);
    Route::post('/conversations/{phone}/send-card-payment-link', [RomaSyncController::class, 'sendCardPaymentLink']);
});
