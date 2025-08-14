<?php

namespace App\Enums;

enum OrderStatus: string
{
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
