<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'Received';
    case AwaitingReview = 'Awaiting Review';
    case Reviewed = 'Reviewed';
    case AwaitingCustomerApproval = 'Awaiting Customer Approval';
    case ReadyForWork = 'Ready for Work';
    case Open = 'Open';
    case InProgress = 'In Progress';
    case ReadyForDelivery = 'Ready for Delivery';
    case Completed = 'Completed';
    case Delivered = 'Delivered';
    case Paid = 'Paid';
    case Returned = 'Returned';
    case NotPaid = 'Not Paid';
    case OnHold = 'On Hold';
    case Cancelled = 'Cancelled';

    public static function getStatuses(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the localized label for this status.
     */
    public function getLabel(): string
    {
        return __('orders.status_labels.'.$this->value);
    }
}
