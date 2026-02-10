<?php

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
    case ReadyForDelivery = 'Ready for delivery';
    case Completed = 'Completed';
    case Delivered = 'Delivered';
    case Paid = 'Paid';
    case Returned = 'Returned';
    case NotPaid = 'Not paid';
    case OnHold = 'On hold';
    case Cancelled = 'Cancelled';

    public static function getStatuses(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::AwaitingReview => 'Awaiting Review',
            self::Reviewed => 'Reviewed',
            self::AwaitingCustomerApproval => 'Awaiting Customer Approval',
            self::ReadyForWork => 'Ready for Work',
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::ReadyForDelivery => 'Ready for delivery',
            self::Completed => 'Completed',
            self::Delivered => 'Delivered',
            self::Paid => 'Paid',
            self::Returned => 'Returned',
            self::NotPaid => 'Not paid',
            self::OnHold => 'On hold',
            self::Cancelled => 'Cancelled'
        };
    }
}
