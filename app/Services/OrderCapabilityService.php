<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

final class OrderCapabilityService
{
    /**
     * Resolve presentation capabilities from the persisted order status and policy result.
     *
     * @return array{
     *     create_order: bool,
     *     submit_budget: bool,
     *     approve_services: bool,
     *     complete_services: bool,
     *     mark_ready_for_delivery: bool,
     *     deliver_order: bool
     * }
     */
    public function for(User $user, Order $order): array
    {
        $canUpdate = $user->can('update', $order);

        return [
            'create_order' => $user->can('create', Order::class),
            'submit_budget' => $canUpdate && $order->status === OrderStatus::AwaitingReview,
            'approve_services' => $user->can('approve', $order)
                && $order->status === OrderStatus::AwaitingCustomerApproval,
            'complete_services' => $canUpdate && in_array($order->status, [
                    OrderStatus::ReadyForWork,
                    OrderStatus::InProgress,
                ], true),
            'mark_ready_for_delivery' => $canUpdate && in_array($order->status, [
                    OrderStatus::ReadyForWork,
                    OrderStatus::InProgress,
                ], true),
            'deliver_order' => $canUpdate
                && $order->status === OrderStatus::ReadyForDelivery
                && !$order->hasPendingPayment(),
        ];
    }
}
