<?php

use App\Http\Controllers\BotSettingsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\DeliveryZoneController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WhatsappMediaController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Broadcast::routes(['middleware' => ['web', 'auth']]);

// Fotos de catálogo accesibles para Meta (sin auth; solo variantes con imagen en disco)
Route::get('/whatsapp-media/variants/{variant}', [WhatsappMediaController::class, 'variant'])
    ->name('whatsapp-media.variant');

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('products', ProductController::class)->only(['index', 'create', 'edit']);
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('delivery-zones', [DeliveryZoneController::class, 'index'])->name('delivery-zones.index');
    Route::get('company-settings', [CompanySettingController::class, 'index'])->name('company-settings.index');
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('bot-settings', [BotSettingsController::class, 'index'])->name('bot-settings.index');
    Route::get('pipeline', function () {
        return Inertia::render('Pipeline/Index');
    })->name('pipeline.index');
    Route::get('customers', function () {
        return Inertia::render('Customers/Index');
    })->name('customers.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
