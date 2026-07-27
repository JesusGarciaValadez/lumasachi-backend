<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

final class OrderAuditNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly Order $order, public readonly string $event) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = Lang::has('notifications.audit.subjects.'.$this->event)
            ? __('notifications.audit.subjects.'.$this->event)
            : __('notifications.audit.subjects.default');
        $event = Lang::has('notifications.audit.events.' . $this->event)
            ? __('notifications.audit.events.' . $this->event)
            : __('notifications.audit.events.default');

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.greeting_admin'))
            ->line(__('notifications.audit.line'))
            ->line(__('notifications.audit.event', ['event' => $event]))
            ->line(__('notifications.audit.order', ['uuid' => $this->order->uuid]))
            ->line(__('notifications.audit.status', ['status' => $this->order->status->getLabel()]))
            ->line(__('notifications.priority_label', ['priority' => $this->order->priority->getLabel()]))
            ->salutation(__('notifications.salutation'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->uuid,
            'status' => $this->order->status->value,
            'event' => $this->event,
        ];
    }
}
