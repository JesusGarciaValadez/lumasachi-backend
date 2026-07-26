<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            /** @var MustVerifyEmail $user */
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Email verified!'], 200);
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
