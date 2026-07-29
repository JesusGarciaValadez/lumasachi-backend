<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderLifecycleStatus: string
{
    case Received = 'Received';
    case AwaitingReview = 'Awaiting Review';
    case Reviewed = 'Reviewed';
    case AwaitingCustomerApproval = 'Awaiting Customer Approval';
    case ReadyForWork = 'Ready for Work';
    case ReadyForDelivery = 'Ready for Delivery';
    case Delivered = 'Delivered';

    public function getLabel(): string
    {
        return __('orders.status_labels.' . $this->value);
    }
}
