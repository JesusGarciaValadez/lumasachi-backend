<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderAuditNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly Order $order, public readonly string $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->event) {
            'created' => 'Audit: Order created',
            'reviewed' => 'Audit: Order reviewed',
            'ready_for_work' => 'Audit: Order ready for work',
            'customer_approved' => 'Audit: Customer approved services',
            'work_completed' => 'Audit: Work completed on order',
            'delivered' => 'Audit: Order delivered',
            'received' => 'Audit: Order received',
            'paid' => 'Audit: Order paid',
            'service_completed' => 'Audit: Service completed',
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
