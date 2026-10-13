<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

final class SessionConnectionContext
{
    /**
     * @param Closure(): Response $next
     */
    public function run(string $connectionName, Closure $next, ?string $guardName = null): Response
    {
        $previousConnection = config('session.connection');
        $previousGuard = (string)config('auth.defaults.guard', 'web');

        $this->forgetResolvedRequestState();
        config(['session.connection' => $connectionName]);
        Auth::shouldUse($guardName ?? $previousGuard);

        try {
            return $next();
        } finally {
            $this->forgetResolvedRequestState();
            config(['session.connection' => $previousConnection]);
            Auth::shouldUse($previousGuard);
        }
    }

    /**
     * Rebuild session services so long-lived workers use the current request's configuration sandbox.
     */
    private function forgetResolvedRequestState(): void
    {
        Auth::forgetGuards();
        app()->forgetInstance(StartSession::class);
        app()->forgetInstance('session');
        app()->forgetInstance('session.store');
        app()->forgetInstance('redirect');
        Redirect::clearResolvedInstance('redirect');
    }
}
