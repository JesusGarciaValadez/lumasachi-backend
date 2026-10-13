<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantReadiness;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;
use Throwable;

final class InitializeReadyTenantBySubdomain extends InitializeTenancyBySubdomain
{
    public function __construct(
        Tenancy                 $tenancy,
        DomainTenantResolver    $resolver,
        private TenantReadiness $tenantReadiness,
    )
    {
        parent::__construct($tenancy, $resolver);
    }

    public function initializeTenancy(mixed $request, mixed $next, mixed ...$resolverArguments): mixed
    {
        try {
            $tenant = $this->resolver->resolve(mb_strtolower($request->getHost()));
        } catch (TenantCouldNotBeIdentifiedException $exception) {
            $onFail = self::$onFail ?? static function (Throwable $exception): never {
                throw $exception;
            };

            return $onFail($exception, $request, $next);
        }

        abort_unless($tenant instanceof Tenant, 404);

        $this->tenantReadiness->assertReady($tenant);
        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
