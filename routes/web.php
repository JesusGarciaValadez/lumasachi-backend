<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderPageController;
use App\Http\Controllers\PublicOrderPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

// Catalog engine options (web)
Route::middleware(['auth', 'verified', 'can:viewAny,App\\Models\\Order'])->get('catalog/engine-options', function () {
    return Inertia::render('Orders/EngineOptions');
})->name('web.catalog.engine-options');

// Public order tracking page; keep it before dynamic order UUID routes.
Route::get('orders/track', [PublicOrderPageController::class, 'show'])->name('web.orders.track');

// Orders index (web) - requires ability to view any orders
Route::middleware(['auth', 'verified', 'can:viewAny,App\\Models\\Order'])->get('orders', [OrderPageController::class, 'index'])
    ->name('web.orders.index');

// Order intake (web) - requires ability to create orders
Route::middleware(['auth', 'verified', 'can:create,App\\Models\\Order'])->get('orders/create', [OrderPageController::class, 'create'])
    ->name('web.orders.create');

// Orders show (web) - render Inertia page with server-side props
Route::middleware(['auth', 'verified', 'can:view,order'])->group(function () {
    Route::get('orders/{order:uuid}', [OrderPageController::class, 'show'])->name('web.orders.show');
});
