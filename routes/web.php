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

Route::middleware(['auth', 'verified', 'can:view,order'])->group(function () {
    Route::get('orders/{order:uuid}', function (App\Models\Order $order) {
        return response()->json(
            new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'category']))
        );
    })->name('orders.show');
});
