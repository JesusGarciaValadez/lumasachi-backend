<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\CentralSuperAdministrator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CentralPasswordResetLinkController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $centralHost = $request->getHost();

        Password::broker('central_super_administrators')->sendResetLink(
            $request->only('email'),
            static function (CentralSuperAdministrator $administrator, string $token) use ($centralHost): void {
                $administrator->notify(new class($token, $centralHost) extends ResetPassword {
                    public function __construct(string $token, private readonly string $centralHost)
                    {
                        parent::__construct($token);
                    }

                    /**
                     * @param CentralSuperAdministrator $notifiable
                     */
                    protected function resetUrl(mixed $notifiable): string
                    {
                        return URL::route('central.password.reset', [
                            'centralHost' => $this->centralHost,
                            'token' => $this->token,
                            'email' => $notifiable->getEmailForPasswordReset(),
                        ]);
                    }
                });
            },
        );

        if ($request->expectsJson()) {
            return response()->json([
                'code' => 'auth.reset_link_sent',
                'message' => __('auth.reset_link_sent'),
            ]);
        }

        return back()->with('status', __('auth.reset_link_sent'));
    }
}
