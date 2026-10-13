<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\CentralTenantAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, CentralTenantAccessService $centralTenantAccess): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $centralOrigin = null;

        if ($user instanceof User && $user->isPlatformSupport()) {
            $grantId = $request->session()->pull('central_access_grant_id');
            $tenant = tenancy()->tenant;
            abort_unless($tenant instanceof Tenant, 403);

            $centralOrigin = $centralTenantAccess->finishSupportSession(
                (string)$tenant->getTenantKey(),
                $user,
                is_string($grantId) ? $grantId : null,
            );
            abort_unless(
                $centralOrigin !== null && $centralTenantAccess->isAllowedCentralOrigin($centralOrigin),
                403,
            );
        }

        if ($request->expectsJson()) {
            if ($user instanceof User) {
                $user->tokens()->delete();
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($centralOrigin !== null) {
                return response()->json([
                    'code' => 'auth.logout',
                    'message' => __('auth.logout'),
                    'redirect' => mb_rtrim($centralOrigin, '/') . '/central',
                ], 200);
            }

            return response()->json(['code' => 'auth.logout', 'message' => __('auth.logout')], 200);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($centralOrigin !== null) {
            return redirect()->away(mb_rtrim($centralOrigin, '/') . '/central');
        }

        return redirect('/');
    }
}
