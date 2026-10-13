<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\CentralSuperAdministrator;
use App\Services\Tenancy\CentralTenantAccessService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CentralNewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'email' => $request->query('email'),
            'token' => $request->route('token'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, CentralTenantAccessService $access): RedirectResponse|JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker('central_super_administrators')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function (CentralSuperAdministrator $administrator) use ($request, $access): void {
                DB::connection($administrator->getConnectionName())->transaction(function () use ($administrator, $request): void {
                    $administrator->forceFill([
                        'password' => Hash::make($request->string('password')->toString()),
                        'must_change_password' => false,
                        'remember_token' => Str::random(60),
                    ])->save();

                    DB::connection($administrator->getConnectionName())
                        ->table('sessions')
                        ->where('user_id', $administrator->getKey())
                        ->delete();
                });

                $access->revokeOutstandingFor($administrator);

                event(new PasswordReset($administrator));
            },
        );

        if ($status === Password::PASSWORD_RESET && $request->expectsJson()) {
            return response()->json([
                'code' => 'auth.password_reset',
                'message' => __($status),
            ]);
        }

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('central.login', [
                'centralHost' => $request->getHost(),
            ])->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
