<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderPageController;
use App\Http\Controllers\PublicOrderPageController;
use App\Http\Controllers\UserAdministrationController;
use App\Http\Resources\UserAdministrationListResource;
use App\Models\User;
use App\Services\UserAdministrationQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('dashboard', function (Request $request, UserAdministrationQuery $query) {
    $actor = $request->user();
    $canViewUsers = $actor instanceof User && $actor->can('viewAny', User::class);
    $recentUsers = $canViewUsers
        ? $query->recent($actor)
            ->map(fn(User $user): array => (new UserAdministrationListResource($user))->resolve($request))
            ->values()
            ->all()
        : [];

    return Inertia::render('Dashboard', [
        'recent_users' => $recentUsers,
        'can_view_users' => $canViewUsers,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('users', [UserAdministrationController::class, 'index'])
        ->can('viewAny', User::class)
        ->name('users.index');

    Route::get('user/create', [UserAdministrationController::class, 'create'])
        ->can('create', User::class)
        ->name('user.create');

    Route::post('user', [UserAdministrationController::class, 'store'])
        ->can('create', User::class)
        ->name('user.store');

    Route::get('user/{user:uuid}', [UserAdministrationController::class, 'show'])
        ->can('viewAdministration', 'user')
        ->name('user.show');

    Route::put('user/{user:uuid}', [UserAdministrationController::class, 'update'])
        ->can('updateAdministration', 'user')
        ->name('user.update');

    Route::delete('user/{user:uuid}', [UserAdministrationController::class, 'destroy'])
        ->can('delete', 'user')
        ->name('user.destroy');
});

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
