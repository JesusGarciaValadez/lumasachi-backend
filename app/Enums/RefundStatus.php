<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'Requested';
    case Approved = 'Approved';
    case Processed = 'Processed';
    case Rejected = 'Rejected';

    public function getLabel(): string
    {
        return __('orders.refund_status_labels.' . $this->value);
    }
}
