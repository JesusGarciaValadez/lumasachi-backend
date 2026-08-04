<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            !$user instanceof User
            || !$user->hasVerifiedEmail()
            || !$user->must_change_password
            || $request->routeIs('password.edit', 'password.update', 'logout', 'verification.*')
        ) {
            return $next($request);
        }

        return to_route('password.edit', ['required' => 1]);
    }
}
