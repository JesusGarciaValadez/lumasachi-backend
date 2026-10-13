<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Auth\CentralAuthenticatedSessionController;
use App\Http\Controllers\Central\Auth\CentralNewPasswordController;
use App\Http\Controllers\Central\Auth\CentralPasswordResetLinkController;
use App\Http\Controllers\Central\CentralTenantAccessController;
use App\Http\Controllers\Central\CompanyOnboardingController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::get('/up', [HealthController::class, 'up'])->name('health.liveness');

Route::middleware('api')->prefix('api/v1')->group(static function (): void {
    Route::get('/up', [HealthController::class, 'up'])->name('health.up');
    Route::get('/health', [HealthController::class, 'health'])->name('health.check');
});

$centralHosts = config('tenancy.central_hosts', []);
$centralHosts = is_array($centralHosts)
    ? array_values(array_unique(array_filter(
        $centralHosts,
        static fn(mixed $host): bool => is_string($host) && $host !== '',
    )))
    : [];

if ($centralHosts !== []) {
    Route::domain('{centralHost}')
        ->whereIn('centralHost', $centralHosts)
        ->middleware('web')
        ->group(static function (): void {
            Route::get('/login', [CentralAuthenticatedSessionController::class, 'create'])
                ->name('central.login');

            Route::post('/login', [CentralAuthenticatedSessionController::class, 'store'])
                ->name('central.login.store');

            Route::post('/logout', [CentralAuthenticatedSessionController::class, 'destroy'])
                ->middleware('auth:central')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->name('central.logout');

            Route::post('/companies/{company}/enter', [CentralTenantAccessController::class, 'store'])
                ->middleware('auth:central')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->name('central.companies.enter');

            Route::get('/companies', [CompanyOnboardingController::class, 'index'])
                ->middleware('auth:central')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->name('central.companies.index');

            Route::post('/companies', [CompanyOnboardingController::class, 'store'])
                ->middleware('auth:central')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->name('central.companies.store');

            Route::post('/companies/{company:uuid}/onboarding/retry', [CompanyOnboardingController::class, 'retry'])
                ->middleware('auth:central')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->name('central.companies.onboarding.retry');

            Route::get('/forgot-password', [CentralPasswordResetLinkController::class, 'create'])
                ->name('central.password.request');

            Route::post('/forgot-password', [CentralPasswordResetLinkController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('central.password.email');

            Route::get('/reset-password/{token}', [CentralNewPasswordController::class, 'create'])
                ->name('central.password.reset');

            Route::post('/reset-password', [CentralNewPasswordController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('central.password.store');

            Route::get('/central', static fn() => response()->view('central.dashboard'))
                ->middleware('auth:central')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->name('central.dashboard');
        });
}
