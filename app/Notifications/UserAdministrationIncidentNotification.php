<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UserAdministrationIncidentNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param array<string, string|null> $context
     */
    public function __construct(public readonly array $context)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('users.notifications.subject'))
            ->greeting(__('notifications.greeting_admin'))
            ->line(__('users.notifications.intro'))
            ->line(__('users.notifications.incident', ['incident' => $this->context['incident_id']]))
            ->line(__('users.notifications.operation', ['operation' => $this->context['operation']]))
            ->line(__('notifications.salutation'));
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return $this->context;
    }
}
