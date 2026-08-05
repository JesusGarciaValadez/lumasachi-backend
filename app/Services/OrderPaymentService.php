<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderHistoryEventType;
use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class OrderPaymentService
{
    public function recordCumulativeDownPayment(
        Order $order,
        string|int|float $targetAmount,
        User $actor,
    ): ?OrderPayment
    {
        $this->assertOrderCanReceivePayment($order);
        $normalizedTarget = $this->normalizeNonnegativeAmount($targetAmount);
        $order->unsetRelation('payments');
        $currentPaid = $order->totalPaid();

        if (bccomp($normalizedTarget, $currentPaid, 2) === -1) {
            throw new InvalidArgumentException('The cumulative down payment cannot be lower than the amount already paid.');
        }

        $difference = bcsub($normalizedTarget, $currentPaid, 2);

        return bccomp($difference, '0.00', 2) === 0
            ? null
            : $this->recordPayment($order, $difference, $actor);
    }

    public function recordPayment(
        Order $order,
        string|int|float $amount,
        User $actor,
        ?CarbonInterface $receivedAt = null,
    ): OrderPayment
    {
        $this->assertOrderCanReceivePayment($order);
        $normalizedAmount = $this->normalizeAmount($amount);

        $payment = $order->payments()->create([
            'amount' => $normalizedAmount,
            'received_at' => $receivedAt ?? now(),
            'created_by' => $actor->id,
        ]);
        $order->unsetRelation('payments');

        OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_PAYMENT_RECORD,
            'event_type' => OrderHistoryEventType::PaymentRecord,
            'old_value' => null,
            'new_value' => $normalizedAmount,
            'created_by' => $actor->id,
        ]);

        return $payment;
    }

    private function assertOrderCanReceivePayment(Order $order): void
    {
        if ($order->lifecycleStatus() === OrderLifecycleStatus::Delivered) {
            throw new InvalidArgumentException('Delivered orders cannot receive new payments.');
        }
    }

    private function normalizeNonnegativeAmount(string|int|float $amount): string
    {
        if (!is_numeric((string)$amount)) {
            throw new InvalidArgumentException('Payment amount must be numeric.');
        }

        $normalizedAmount = bcadd((string)$amount, '0.00', 2);

        if (bccomp($normalizedAmount, '0.00', 2) === -1) {
            throw new InvalidArgumentException('Payment amount cannot be negative.');
        }

        return $normalizedAmount;
    }

    private function normalizeAmount(string|int|float $amount): string
    {
        $normalizedAmount = $this->normalizeNonnegativeAmount($amount);

        if (bccomp($normalizedAmount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return $normalizedAmount;
    }
}
