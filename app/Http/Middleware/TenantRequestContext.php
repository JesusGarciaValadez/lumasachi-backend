<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\SessionConnectionContext;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

final class TenantRequestContext
{
    public function __construct(
        private ResetTenantContext               $resetTenantContext,
        private PreventAccessFromCentralDomains  $preventAccessFromCentralDomains,
        private InitializeReadyTenantBySubdomain $initializeReadyTenantBySubdomain,
        private SessionConnectionContext         $sessionConnectionContext,
    )
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') && config('tenancy.allow_legacy_test_routes')) {
            return $next($request);
        }

        return $this->resetTenantContext->handle($request, function (Request $request) use ($next): Response {
            $centralHosts = array_map(
                static fn(string $host): string => mb_strtolower(mb_trim($host)),
                config('tenancy.central_hosts', []),
            );

            abort_if(in_array(mb_strtolower($request->getHost()), $centralHosts, true), 404);

            return $this->preventAccessFromCentralDomains->handle($request, function (Request $request) use ($next): Response {
                return $this->initializeReadyTenantBySubdomain->handle(
                    $request,
                    fn(Request $request): Response => $this->sessionConnectionContext->run(
                        'tenant',
                        fn(): Response => $next($request),
                    ),
                );
            });
        });
    }
}
