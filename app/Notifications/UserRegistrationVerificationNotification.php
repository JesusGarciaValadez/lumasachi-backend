<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use LogicException;

final class UserRegistrationVerificationNotification extends Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (!$notifiable instanceof User) {
            throw new LogicException('Registration verification notifications require a tenant user.');
        }

        $verificationUrl = URL::temporarySignedRoute(
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
            ->lineIf(
                $notifiable->must_change_password,
                __('auth.registration_verification_password'),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
