<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Unpaid = 'Unpaid';
    case PartiallyPaid = 'Partially Paid';
    case Paid = 'Paid';

    public function getLabel(): string
    {
        return __('orders.payment_status_labels.' . $this->value);
    }
}
