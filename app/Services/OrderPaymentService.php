<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class OrderPaymentService
{
    public function recordCumulativeDownPayment(
        Order            $order,
        string|int|float $targetAmount,
        User             $actor,
    ): ?OrderPayment
    {
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

    public function recordPayment(
        Order            $order,
        string|int|float $amount,
        User             $actor,
        ?CarbonInterface $receivedAt = null,
    ): OrderPayment
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        $payment = $order->payments()->create([
            'amount' => $normalizedAmount,
            'received_at' => $receivedAt ?? now(),
            'created_by' => $actor->id,
        ]);
        $order->unsetRelation('payments');

        return $payment;
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
