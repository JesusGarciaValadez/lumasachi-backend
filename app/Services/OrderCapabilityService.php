<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderLifecycleStatus;
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
     *     deliver_order: bool,
     *     cancel_order: bool
     * }
     */
    public function for(User $user, Order $order): array
    {
        $canUpdate = $user->can('update', $order);

        return [
            'create_order' => $user->can('create', Order::class),
            'submit_budget' => $canUpdate && $order->lifecycleStatus() === OrderLifecycleStatus::AwaitingReview,
            'approve_services' => $user->can('approve', $order)
                && $order->lifecycleStatus() === OrderLifecycleStatus::AwaitingCustomerApproval,
            'complete_services' => $canUpdate && $order->lifecycleStatus() === OrderLifecycleStatus::ReadyForWork,
            'mark_ready_for_delivery' => $canUpdate && $order->lifecycleStatus() === OrderLifecycleStatus::ReadyForWork,
            'deliver_order' => $canUpdate
                && $order->lifecycleStatus() === OrderLifecycleStatus::ReadyForDelivery,
            'cancel_order' => $user->can('cancel', $order) && $order->dispositionStatus() === null,
        ];
    }
}
