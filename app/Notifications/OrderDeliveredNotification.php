<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Jobs\Middleware\TenantNotificationContext;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderDeliveredNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @return list<class-string<TenantNotificationContext>> */
    public function middleware(object $notifiable, string $channel): array
    {
        return [TenantNotificationContext::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.order_delivered.subject'))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.order_delivered.line'))
            ->line(__('notifications.order_label', ['uuid' => $this->order->uuid]))
            ->line(__('notifications.status_label', ['status' => $this->order->lifecycleStatus()?->getLabel()]))
            ->action(__('notifications.view_order'), route('web.orders.show', $this->order))
            ->salutation(__('notifications.salutation'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->uuid,
            'status' => $this->order->lifecycleStatus()?->value,
        ];
    }
}
