<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderAuditNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly Order $order, public readonly string $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->event) {
            'created' => 'Audit: Order created',
            'reviewed' => 'Audit: Order reviewed',
'delivered' => 'Audit: Order delivered',
            'received' => 'Audit: Order received',
            default => 'Audit: Order event',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello Admin')
            ->line('An auditable event occurred for an order:')
            ->line('Event: '.$this->event)
            ->line('Order: '.$this->order->uuid)
            ->line('Status: '.$this->order->status->value)
            ->salutation('Regards');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->uuid,
            'status' => $this->order->status->value,
            'event' => $this->event,
        ];
    }
}
