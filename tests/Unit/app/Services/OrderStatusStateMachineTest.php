<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Services\OrderStatusStateMachine;

test('permitted lifecycle transition is allowed', function (
    OrderLifecycleStatus $current,
    OrderLifecycleStatus $new,
): void {
    $stateMachine = new OrderStatusStateMachine();

    $this->assertTrue($stateMachine->canTransition($current, $new));
    $this->assertContains($new, $stateMachine->nextStatuses($current));
})->with(orderStatusStateMachinePermittedTransitions(...));

test('rejected lifecycle transitions are not allowed', function (): void {
    $stateMachine = new OrderStatusStateMachine();

    $this->assertFalse($stateMachine->canTransition(OrderLifecycleStatus::Received, OrderLifecycleStatus::Delivered));
    $this->assertFalse($stateMachine->canTransition(OrderLifecycleStatus::ReadyForWork, OrderLifecycleStatus::Received));
    $this->assertFalse($stateMachine->canTransition(OrderLifecycleStatus::Delivered, OrderLifecycleStatus::ReadyForWork));
});

test('unknown lifecycle values are rejected', function (): void {
    $stateMachine = new OrderStatusStateMachine();

    $this->assertFalse($stateMachine->canTransitionValues('Unknown', OrderLifecycleStatus::Received->value));
    $this->assertFalse($stateMachine->canTransitionValues(OrderLifecycleStatus::Received->value, 'Unknown'));
});

/**
 * @return iterable<string, array{OrderLifecycleStatus, OrderLifecycleStatus}>
 */
function orderStatusStateMachinePermittedTransitions(): iterable
{
    yield from orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus::Received, [OrderLifecycleStatus::AwaitingReview]);
    yield from orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus::AwaitingReview, [OrderLifecycleStatus::Reviewed]);
    yield from orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus::Reviewed, [OrderLifecycleStatus::AwaitingCustomerApproval]);
    yield from orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus::AwaitingCustomerApproval, [OrderLifecycleStatus::ReadyForWork]);
    yield from orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus::ReadyForWork, [OrderLifecycleStatus::ReadyForDelivery]);
    yield from orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus::ReadyForDelivery, [OrderLifecycleStatus::Delivered]);
}

/**
 * @param list<OrderLifecycleStatus> $newStatuses
 * @return iterable<string, array{OrderLifecycleStatus, OrderLifecycleStatus}>
 */
function orderStatusStateMachineTransitionsFrom(OrderLifecycleStatus $current, array $newStatuses): iterable
{
    foreach ($newStatuses as $new) {
        yield $current->value . ' -> ' . $new->value => [$current, $new];
    }
}
