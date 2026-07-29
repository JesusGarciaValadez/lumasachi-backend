<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services;

use App\Enums\OrderStatus;
use App\Services\OrderStatusStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OrderStatusStateMachineTest extends TestCase
{
    /**
     * @return iterable<string, array{OrderStatus, OrderStatus}>
     */
    public static function permittedTransitions(): iterable
    {
        yield from self::transitionsFrom(OrderStatus::Received, [OrderStatus::AwaitingReview, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::AwaitingReview, [OrderStatus::Reviewed, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::Reviewed, [OrderStatus::AwaitingCustomerApproval, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::AwaitingCustomerApproval, [OrderStatus::ReadyForWork, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::ReadyForWork, [OrderStatus::InProgress, OrderStatus::ReadyForDelivery, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::Open, [OrderStatus::InProgress, OrderStatus::Cancelled, OrderStatus::OnHold]);
        yield from self::transitionsFrom(OrderStatus::InProgress, [OrderStatus::ReadyForDelivery, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::OnHold]);
        yield from self::transitionsFrom(OrderStatus::OnHold, [OrderStatus::InProgress, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::ReadyForDelivery, [OrderStatus::Delivered, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::Delivered, [OrderStatus::Paid, OrderStatus::Returned, OrderStatus::NotPaid]);
        yield from self::transitionsFrom(OrderStatus::NotPaid, [OrderStatus::Paid, OrderStatus::Cancelled]);
        yield from self::transitionsFrom(OrderStatus::Returned, [OrderStatus::Cancelled]);
    }

    #[DataProvider('permittedTransitions')]
    public function test_permitted_status_transition_is_allowed(OrderStatus $current, OrderStatus $new): void
    {
        $stateMachine = new OrderStatusStateMachine();

        $this->assertTrue($stateMachine->canTransition($current, $new));
        $this->assertContains($new, $stateMachine->nextStatuses($current));
    }

    public function test_rejected_status_transition_is_not_allowed(): void
    {
        $stateMachine = new OrderStatusStateMachine();

        $this->assertFalse($stateMachine->canTransition(OrderStatus::Received, OrderStatus::Delivered));
        $this->assertFalse($stateMachine->canTransition(OrderStatus::InProgress, OrderStatus::Open));
        $this->assertFalse($stateMachine->canTransition(OrderStatus::Paid, OrderStatus::InProgress));
        $this->assertFalse($stateMachine->canTransition(OrderStatus::Cancelled, OrderStatus::Received));
    }

    public function test_unknown_status_values_are_rejected(): void
    {
        $stateMachine = new OrderStatusStateMachine();

        $this->assertFalse($stateMachine->canTransitionValues('Unknown', OrderStatus::Received->value));
        $this->assertFalse($stateMachine->canTransitionValues(OrderStatus::Received->value, 'Unknown'));
    }

    /**
     * @param list<OrderStatus> $newStatuses
     * @return iterable<string, array{OrderStatus, OrderStatus}>
     */
    private static function transitionsFrom(OrderStatus $current, array $newStatuses): iterable
    {
        foreach ($newStatuses as $new) {
            yield $current->value . ' -> ' . $new->value => [$current, $new];
        }
    }
}
