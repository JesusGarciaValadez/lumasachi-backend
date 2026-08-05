<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

final class OrderStatusStateMachine
{
    /**
     * @var array<string, list<OrderLifecycleStatus>>
     */
    private const TRANSITIONS = [
        OrderLifecycleStatus::Received->value => [OrderLifecycleStatus::AwaitingReview],
        OrderLifecycleStatus::AwaitingReview->value => [OrderLifecycleStatus::Reviewed],
        OrderLifecycleStatus::Reviewed->value => [OrderLifecycleStatus::AwaitingCustomerApproval],
        OrderLifecycleStatus::AwaitingCustomerApproval->value => [OrderLifecycleStatus::ReadyForWork],
        OrderLifecycleStatus::ReadyForWork->value => [OrderLifecycleStatus::ReadyForDelivery],
        OrderLifecycleStatus::ReadyForDelivery->value => [OrderLifecycleStatus::Delivered],
        OrderLifecycleStatus::Delivered->value => [],
    ];

    /**
     * Determine whether two stored values represent an allowed lifecycle transition.
     */
    public function canTransitionValues(string $currentStatus, string $newStatus): bool
    {
        $current = OrderLifecycleStatus::tryFrom($currentStatus);
        $new = OrderLifecycleStatus::tryFrom($newStatus);

        return $current !== null
            && $new !== null
            && $this->canTransition($current, $new);
    }

    /**
     * Determine whether the requested lifecycle transition is allowed.
     */
    public function canTransition(OrderLifecycleStatus $currentStatus, OrderLifecycleStatus $newStatus): bool
    {
        return $currentStatus === $newStatus
            || in_array($newStatus, $this->nextStatuses($currentStatus), true);
    }

    /**
     * Get the next permitted lifecycle statuses.
     *
     * @return list<OrderLifecycleStatus>
     */
    public function nextStatuses(OrderLifecycleStatus $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus->value] ?? [];
    }

    /**
     * Transition an order through its lifecycle.
     *
     * @param array<string, mixed> $additionalAttributes
     *
     * @throws InvalidArgumentException
     */
    public function transition(
        Order $order,
        OrderLifecycleStatus $newStatus,
        User $actor,
        array $additionalAttributes = [],
    ): Order
    {
        $this->assertOrderCanChangeLifecycle($order);
        $this->assertCanTransition($order->lifecycleStatus(), $newStatus);

        $order->update(array_merge($additionalAttributes, [
            'lifecycle_status' => $newStatus->value,
            'updated_by' => $actor->id,
        ]));

        return $order;
    }

    /**
     * Persist an internally-managed lifecycle transition without firing model events.
     *
     * @throws InvalidArgumentException
     */
    public function transitionQuietly(Order $order, OrderLifecycleStatus $newStatus): void
    {
        $this->assertOrderCanChangeLifecycle($order);
        $this->assertCanTransition($order->lifecycleStatus(), $newStatus);

        $order->updateQuietly(['lifecycle_status' => $newStatus->value]);
    }

    /**
     * Set a terminal order disposition.
     *
     * @throws InvalidArgumentException
     */
    public function setDisposition(
        Order   $order,
        OrderDispositionStatus $disposition,
        User    $actor,
        ?string $note = null,
    ): Order
    {
        if ($order->lifecycleStatus() === OrderLifecycleStatus::Delivered) {
            throw new InvalidArgumentException(__('orders.validation.delivered_notes_only'));
        }

        if ($order->dispositionStatus() !== null) {
            throw new InvalidArgumentException('A terminal order disposition cannot be changed.');
        }

        $attributes = [
            'disposition_status' => $disposition->value,
            'updated_by' => $actor->id,
        ];

        if ($note !== null) {
            $attributes['notes'] = $this->appendNote($order->notes, $note);
        }

        $order->update($attributes);

        return $order;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertOrderCanChangeLifecycle(Order $order): void
    {
        if ($order->dispositionStatus() !== null) {
            throw new InvalidArgumentException('A terminal order disposition cannot resume the lifecycle.');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertCanTransition(?OrderLifecycleStatus $currentStatus, OrderLifecycleStatus $newStatus): void
    {
        if ($currentStatus !== null && $this->canTransition($currentStatus, $newStatus)) {
            return;
        }

        $currentValue = $currentStatus?->value ?? 'Unknown';

        throw new InvalidArgumentException(
            "Invalid lifecycle transition from [{$currentValue}] to [{$newStatus->value}]."
        );
    }

    private function appendNote(?string $existingNotes, string $note): string
    {
        return blank($existingNotes) ? $note : $existingNotes . "\n" . $note;
    }
}
