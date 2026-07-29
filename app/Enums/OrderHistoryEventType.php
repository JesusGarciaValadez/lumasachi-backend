<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderHistoryEventType: string
{
    case Attribute = 'attribute';
    case Lifecycle = 'lifecycle';
    case Disposition = 'disposition';
    case Payment = 'payment';
    case PaymentRecord = 'payment_record';
    case Refund = 'refund';

    public function getLabel(): string
    {
        return __('orders.history_event_types.' . $this->value);
    }
}
