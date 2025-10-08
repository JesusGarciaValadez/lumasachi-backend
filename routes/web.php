<?php

use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

// Catalog engine options (web)
Route::middleware(['auth', 'verified', 'can:viewAny,App\\Models\\Order'])->get('catalog/engine-options', function () {
    return Inertia::render('Orders/EngineOptions');
})->name('web.catalog.engine-options');

// Orders index (web) - requires ability to view any orders
Route::middleware(['auth', 'verified', 'can:viewAny,App\\Models\\Order'])->get('orders', function () {
    return Inertia::render('Orders/Index');
})->name('web.orders.index');

// Orders show (web) - render Inertia page with server-side props
Route::middleware(['auth', 'verified', 'can:view,order'])->group(function () {
    Route::get('orders/{order:uuid}', function (App\Models\Order $order) {
        return Inertia::render('Orders/Show', [
            // Resolve the resource to avoid { data: {...} } wrapping in props
            'order' => (new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'categories'])))->resolve(),
        ]);
    })->name('web.orders.show');
});
