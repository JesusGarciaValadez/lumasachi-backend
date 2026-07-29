<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services;

use App\Enums\OrderLifecycleStatus;
use App\Services\OrderStatusStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OrderStatusStateMachineTest extends TestCase
{
    /**
     * @return iterable<string, array{OrderLifecycleStatus, OrderLifecycleStatus}>
     */
    public static function permittedTransitions(): iterable
    {
        yield from self::transitionsFrom(OrderLifecycleStatus::Received, [OrderLifecycleStatus::AwaitingReview]);
        yield from self::transitionsFrom(OrderLifecycleStatus::AwaitingReview, [OrderLifecycleStatus::Reviewed]);
        yield from self::transitionsFrom(OrderLifecycleStatus::Reviewed, [OrderLifecycleStatus::AwaitingCustomerApproval]);
        yield from self::transitionsFrom(OrderLifecycleStatus::AwaitingCustomerApproval, [OrderLifecycleStatus::ReadyForWork]);
        yield from self::transitionsFrom(OrderLifecycleStatus::ReadyForWork, [OrderLifecycleStatus::ReadyForDelivery]);
        yield from self::transitionsFrom(OrderLifecycleStatus::ReadyForDelivery, [OrderLifecycleStatus::Delivered]);
    }

    #[DataProvider('permittedTransitions')]
    public function test_permitted_lifecycle_transition_is_allowed(
        OrderLifecycleStatus $current,
        OrderLifecycleStatus $new,
    ): void
    {
        $stateMachine = new OrderStatusStateMachine();

        $this->assertTrue($stateMachine->canTransition($current, $new));
        $this->assertContains($new, $stateMachine->nextStatuses($current));
    }

    public function test_rejected_lifecycle_transitions_are_not_allowed(): void
    {
        $stateMachine = new OrderStatusStateMachine();

        $this->assertFalse($stateMachine->canTransition(OrderLifecycleStatus::Received, OrderLifecycleStatus::Delivered));
        $this->assertFalse($stateMachine->canTransition(OrderLifecycleStatus::ReadyForWork, OrderLifecycleStatus::Received));
        $this->assertFalse($stateMachine->canTransition(OrderLifecycleStatus::Delivered, OrderLifecycleStatus::ReadyForWork));
    }

    public function test_unknown_lifecycle_values_are_rejected(): void
    {
        $stateMachine = new OrderStatusStateMachine();

        $this->assertFalse($stateMachine->canTransitionValues('Unknown', OrderLifecycleStatus::Received->value));
        $this->assertFalse($stateMachine->canTransitionValues(OrderLifecycleStatus::Received->value, 'Unknown'));
    }

    /**
     * @param list<OrderLifecycleStatus> $newStatuses
     * @return iterable<string, array{OrderLifecycleStatus, OrderLifecycleStatus}>
     */
    private static function transitionsFrom(OrderLifecycleStatus $current, array $newStatuses): iterable
    {
        foreach ($newStatuses as $new) {
            yield $current->value . ' -> ' . $new->value => [$current, $new];
        }
    }
}
