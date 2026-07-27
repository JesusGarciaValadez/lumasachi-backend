<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['code' => 'auth.email_already_verified', 'message' => __('auth.email_already_verified')], 200);
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        $user->sendEmailVerificationNotification();

        if ($request->expectsJson()) {
            return response()->json(['code' => 'auth.verification_link_sent', 'message' => __('auth.verification_link_sent')], 200);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
