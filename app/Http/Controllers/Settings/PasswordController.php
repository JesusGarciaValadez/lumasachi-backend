<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Password', [
            'mustChangePassword' => (bool)$request->user()?->must_change_password,
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];

        if (!$user->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validate($rules);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['code' => 'auth.password_updated', 'message' => __('auth.password_updated')], 200);
        }

        return back();
    }
}
