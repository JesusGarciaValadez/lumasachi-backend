<?php

namespace App\Enums;

enum OrderStatus: string
{
    case RECEIVED = 'Received';
    case AWAITING_REVIEW = 'Awaiting Review';
    case REVIEWED = 'Reviewed';
    case AWAITING_CUSTOMER_APPROVAL = 'Awaiting Customer Approval';
    case READY_FOR_WORK = 'Ready for Work';
    case OPEN = 'Open';
    case IN_PROGRESS = 'In Progress';
    case READY_FOR_DELIVERY = 'Ready for delivery';
    case COMPLETED = 'Completed';
    case DELIVERED = 'Delivered';
    case PAID = 'Paid';
    case RETURNED = 'Returned';
    case NOT_PAID = 'Not paid';
    case ON_HOLD = 'On hold';
    case CANCELLED = 'Cancelled';

    public static function getStatuses(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received',
            self::AWAITING_REVIEW => 'Awaiting Review',
            self::REVIEWED => 'Reviewed',
            self::AWAITING_CUSTOMER_APPROVAL => 'Awaiting Customer Approval',
            self::READY_FOR_WORK => 'Ready for Work',
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::READY_FOR_DELIVERY => 'Ready for delivery',
            self::COMPLETED => 'Completed',
            self::DELIVERED => 'Delivered',
            self::PAID => 'Paid',
            self::RETURNED => 'Returned',
            self::NOT_PAID => 'Not paid',
            self::ON_HOLD => 'On hold',
            self::CANCELLED => 'Cancelled'
        };
    }
}
