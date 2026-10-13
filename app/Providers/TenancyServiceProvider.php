<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LogicException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Controllers\TenantAssetsController;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Throwable;

final class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->assertTenantParentDomainsDoNotOverlap();
        TenantAssetsController::$tenancyMiddleware = \App\Http\Middleware\TenantRequestContext::class;
        DatabaseConfig::generateUsernamesUsing(static fn(TenantWithDatabase $tenant): string => Str::random(16));
        DatabaseConfig::generatePasswordsUsing(static fn(TenantWithDatabase $tenant): string => Str::random(64));
        InitializeTenancyBySubdomain::$onFail = static function (Throwable $exception): never {
            abort(404);
        };

        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    private function assertTenantParentDomainsDoNotOverlap(): void
    {
        $configuredDomains = config('tenancy.central_domains', []);

        if (!is_array($configuredDomains)) {
            throw new LogicException('Tenant parent domains must be configured as a list.');
        }

        $tenantParentDomains = array_values(array_filter($configuredDomains, 'is_string'));

        foreach ($tenantParentDomains as $tenantParentDomain) {
            foreach ($tenantParentDomains as $otherTenantParentDomain) {
                if ($tenantParentDomain !== $otherTenantParentDomain
                    && str_ends_with($tenantParentDomain, '.' . $otherTenantParentDomain)) {
                    throw new LogicException('Tenant parent domains must not overlap or be nested.');
                }
            }
        }
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /** @return array<class-string, list<class-string>> */
    public function events(): array
    {
        return [
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
        ];
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            \App\Http\Middleware\TenantRequestContext::class,
            \App\Http\Middleware\RequireCentralDomain::class,
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            \App\Http\Middleware\InitializeReadyTenantBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app->make(\Illuminate\Foundation\Http\Kernel::class)->prependToMiddlewarePriority($middleware);
        }
    }
}
