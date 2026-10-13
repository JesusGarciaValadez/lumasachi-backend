<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\SessionConnectionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireCentralDomain
{
    public function __construct(private SessionConnectionContext $sessionConnectionContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $centralHosts = array_map(
            static fn(string $host): string => mb_strtolower(mb_trim($host)),
            config('tenancy.central_hosts', []),
        );

        abort_unless(in_array(mb_strtolower($request->getHost()), $centralHosts, true), 404);

        $centralConnection = config('tenancy.database.central_connection');
        abort_unless(is_string($centralConnection) && $centralConnection !== '', 404);

        return $this->sessionConnectionContext->run(
            $centralConnection,
            fn(): Response => $next($request),
            'central',
        );
    }
}
