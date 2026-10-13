<?php

declare(strict_types=1);

use App\Http\Middleware\RequireActiveCentralSupportActor;
use App\Http\Middleware\TenantRequestContext;
use Illuminate\Support\Facades\Route;

Route::middleware(TenantRequestContext::class)->group(static function (): void {
    Route::middleware(['web', RequireActiveCentralSupportActor::class])->group(base_path('routes/web.php'));
    Route::middleware(['api', RequireActiveCentralSupportActor::class])->prefix('api')->group(base_path('routes/api.php'));
});
