<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\CentralSuperAdministrator;
use App\Services\Tenancy\CentralTenantAccessService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CentralAuthenticatedSessionController extends Controller
{
    private const int MAX_LOGIN_ATTEMPTS = 5;

    private const int LOGIN_LOCKOUT_SECONDS = 60;

    public function create(Request $request): Response|RedirectResponse
    {
        if (Auth::guard('central')->check()) {
            return redirect()->route('central.dashboard', ['centralHost' => $request->getHost()]);
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('central.password.request'),
            'loginUrl' => route('central.login.store', ['centralHost' => $request->getHost()]),
            'passwordResetUrl' => route('central.password.request', ['centralHost' => $request->getHost()]),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (Auth::guard('central')->check()) {
            return redirect()->route('central.dashboard', ['centralHost' => $request->getHost()]);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ])],
            ]);
        }

        if (!Auth::guard('central')->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->route('central.dashboard', ['centralHost' => $request->getHost()]);
    }

    private function throttleKey(Request $request): string
    {
        return 'central|' . Str::transliterate(Str::lower($request->string('email')->toString()) . '|' . $request->ip());
    }

    public function destroy(Request $request, CentralTenantAccessService $access): RedirectResponse
    {
        $administrator = Auth::guard('central')->user();

        if ($administrator instanceof CentralSuperAdministrator) {
            $access->revokeOutstandingFor($administrator);
        }

        Auth::guard('central')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('central.login', ['centralHost' => $request->getHost()]);
    }
}
