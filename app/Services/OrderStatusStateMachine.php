<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

final class OrderStatusStateMachine
{
    /**
     * The current transition map is retained for compatibility with existing
     * application behavior until the unresolved legacy/payment decisions are
     * settled in the subsequent plan steps.
     *
     * @var array<string, list<OrderStatus>>
     */
    private const TRANSITIONS = [
        OrderStatus::Received->value => [OrderStatus::AwaitingReview, OrderStatus::Cancelled],
        OrderStatus::AwaitingReview->value => [OrderStatus::Reviewed, OrderStatus::Cancelled],
        OrderStatus::Reviewed->value => [OrderStatus::AwaitingCustomerApproval, OrderStatus::Cancelled],
        OrderStatus::AwaitingCustomerApproval->value => [OrderStatus::ReadyForWork, OrderStatus::Cancelled],
        OrderStatus::ReadyForWork->value => [OrderStatus::InProgress, OrderStatus::ReadyForDelivery, OrderStatus::Cancelled],
        OrderStatus::Open->value => [OrderStatus::InProgress, OrderStatus::Cancelled, OrderStatus::OnHold],
        OrderStatus::InProgress->value => [OrderStatus::ReadyForDelivery, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::OnHold],
        OrderStatus::OnHold->value => [OrderStatus::InProgress, OrderStatus::Cancelled],
        OrderStatus::ReadyForDelivery->value => [OrderStatus::Delivered, OrderStatus::Cancelled],
        OrderStatus::Delivered->value => [OrderStatus::Paid, OrderStatus::Returned, OrderStatus::NotPaid],
        OrderStatus::Paid->value => [],
        OrderStatus::Returned->value => [OrderStatus::Cancelled],
        OrderStatus::NotPaid->value => [OrderStatus::Paid, OrderStatus::Cancelled],
        OrderStatus::Cancelled->value => [],
        OrderStatus::Completed->value => [],
    ];

    /**
     * Determine whether two stored values represent an allowed transition.
     */
    public function canTransitionValues(string $currentStatus, string $newStatus): bool
    {
        $current = OrderStatus::tryFrom($currentStatus);
        $new = OrderStatus::tryFrom($newStatus);

        return $current !== null
            && $new !== null
            && $this->canTransition($current, $new);
    }

    /**
     * Determine whether the requested transition is allowed.
     */
    public function canTransition(OrderStatus $currentStatus, OrderStatus $newStatus): bool
    {
        return $currentStatus === $newStatus
            || in_array($newStatus, $this->nextStatuses($currentStatus), true);
    }

    /**
     * Get the next permitted statuses for the current status.
     *
     * @return list<OrderStatus>
     */
    public function nextStatuses(OrderStatus $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus->value] ?? [];
    }

    /**
     * Transition an order and persist the actor responsible for the change.
     *
     * @throws InvalidArgumentException
     */
    /**
     * @param array<string, mixed> $additionalAttributes
     *
     * @throws InvalidArgumentException
     */
    public function transition(
        Order $order,
        OrderStatus $newStatus,
        User $actor,
        array $additionalAttributes = [],
    ): Order
    {
        $this->assertCanTransition($order->status, $newStatus);

        $order->update(array_merge($additionalAttributes, Order::domainStatusAttributes($newStatus), [
            'status' => $newStatus->value,
            'updated_by' => $actor->id,
        ]));

        return $order;
    }

    /**
     * Persist an internally-managed transition without firing model events.
     *
     * The observer uses this only for the documented automatic
     * Reviewed → Awaiting Customer Approval transition, whose history row is
     * recorded explicitly by that observer.
     *
     * @throws InvalidArgumentException
     */
    public function transitionQuietly(Order $order, OrderStatus $newStatus): void
    {
        $this->assertCanTransition($order->status, $newStatus);

        $order->updateQuietly(array_merge(
            ['status' => $newStatus->value],
            Order::domainStatusAttributes($newStatus),
        ));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertCanTransition(OrderStatus $currentStatus, OrderStatus $newStatus): void
    {
        if ($this->canTransition($currentStatus, $newStatus)) {
            return;
        }

        throw new InvalidArgumentException(
            "Invalid status transition from [{$currentStatus->value}] to [{$newStatus->value}]."
        );
    }
}
