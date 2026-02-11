<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.order_delivered.subject'))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.order_delivered.line'))
            ->line(__('notifications.order_label', ['uuid' => $this->order->uuid]))
            ->line(__('notifications.status_label', ['status' => $this->order->status->value]))
            ->action(__('notifications.view_order'), route('web.orders.show', $this->order))
            ->salutation(__('notifications.salutation'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->uuid,
            'status' => $this->order->status->value,
        ];
    }
}
