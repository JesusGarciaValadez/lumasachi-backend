<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ReflectionProperty;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Symfony\Component\HttpFoundation\Response;

final class ResetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->reset();

        try {
            return $next($request);
        } finally {
            $this->reset();
        }
    }

    private function reset(): void
    {
        tenancy()->end();
        (new ReflectionProperty(DomainTenantResolver::class, 'currentDomain'))->setValue(null, null);
    }
}
