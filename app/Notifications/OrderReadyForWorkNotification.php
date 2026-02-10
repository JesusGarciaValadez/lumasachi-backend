<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderReadyForWorkNotification extends Notification implements ShouldQueue
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
            ->subject('Your order has been approved and is ready for work')
            ->greeting('Hello!')
            ->line('Your work order has been approved and work will begin shortly.')
            ->line('Order: '.$this->order->uuid)
            ->line('Status: '.$this->order->status->value)
            ->salutation('Regards');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->uuid,
            'status' => $this->order->status->value,
        ];
    }
}
