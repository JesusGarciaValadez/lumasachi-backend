<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrationVerificationRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class VerifyEmailController extends Controller
{
    /**
     * Mark the user identified by the signed registration link as verified.
     */
    public function __invoke(RegistrationVerificationRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->verificationUser();
        abort_unless($user instanceof User, 404);

        $this->authenticateWebRecipient($request, $user);

        if ($user->hasVerifiedEmail()) {
            if ($user->must_change_password && !$request->expectsJson()) {
                return to_route('password.edit', ['required' => 1]);
            }

            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            /** @var MustVerifyEmail $user */
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'code' => 'auth.email_verified',
                'message' => __('auth.email_verified'),
                'must_change_password' => (bool)$user->must_change_password,
            ], 200);
        }

        if ($user->must_change_password) {
            return to_route('password.edit', ['required' => 1]);
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }

    private function authenticateWebRecipient(RegistrationVerificationRequest $request, User $user): void
    {
        if ($request->expectsJson() || !$request->hasSession()) {
            return;
        }

        $authenticatedUser = $request->user();

        if ($authenticatedUser instanceof User && $authenticatedUser->is($user)) {
            return;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }
}
