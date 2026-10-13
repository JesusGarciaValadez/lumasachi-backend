<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

final class TenantAdministratorInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tenantDomain)
    {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (!$notifiable instanceof User) {
            throw new InvalidArgumentException('Tenant administrator invitations can only be sent to a tenant user.');
        }

        $applicationUrl = parse_url((string)config('app.url'));
        $scheme = is_array($applicationUrl) && in_array($applicationUrl['scheme'] ?? null, ['https', 'http'], true)
            ? $applicationUrl['scheme']
            : null;

        if ($scheme === null) {
            throw new InvalidArgumentException('The application URL must use HTTP or HTTPS to create a tenant invitation.');
        }

        $port = is_array($applicationUrl) && isset($applicationUrl['port']) ? ':' . $applicationUrl['port'] : '';
        $request = Request::create($scheme . '://' . $this->tenantDomain . $port);
        $urlGenerator = new UrlGenerator(Route::getRoutes(), $request, (string)config('app.asset_url'));
        $urlGenerator->setKeyResolver(static fn(): string => (string)config('app.key'));
        $verificationUrl = $urlGenerator->temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int)config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject(__('auth.registration_verification_subject'))
            ->greeting(__('auth.registration_verification_greeting', ['name' => $notifiable->first_name]))
            ->line(__('auth.registration_verification_email', ['email' => $notifiable->getEmailForVerification()]))
            ->line(__('auth.registration_verification_intro'))
            ->action(__('auth.registration_verification_action'), $verificationUrl)
            ->line(__('auth.registration_verification_password'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
