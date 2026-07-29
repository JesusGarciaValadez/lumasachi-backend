<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OrderRefundService
{
    public function requestRefund(
        Order            $order,
        string|int|float $amount,
        string           $reason,
        ?OrderPayment    $sourcePayment,
        User             $requester,
    ): OrderRefund
    {
        if (!in_array($order->status, [OrderStatus::Returned, OrderStatus::Cancelled], true)) {
            throw new InvalidArgumentException('Refunds can only be requested for returned or cancelled orders.');
        }

        if ($sourcePayment && $sourcePayment->order_id !== $order->id) {
            throw new InvalidArgumentException('The source payment must belong to the order.');
        }

        return $order->refunds()->create([
            'source_payment_id' => $sourcePayment?->id,
            'amount' => $this->normalizeAmount($amount),
            'status' => RefundStatus::Requested->value,
            'reason' => $reason,
            'requested_by' => $requester->id,
            'requested_at' => now(),
        ]);
    }

    private function normalizeAmount(string|int|float $amount): string
    {
        if (!is_numeric((string)$amount)) {
            throw new InvalidArgumentException('Refund amount must be numeric.');
        }

        $normalizedAmount = bcadd((string)$amount, '0.00', 2);

        if (bccomp($normalizedAmount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return $normalizedAmount;
    }

    public function approveRefund(OrderRefund $refund, User $approver): OrderRefund
    {
        if (!$this->canReview($approver, $refund)) {
            throw new InvalidArgumentException('This user cannot approve the refund request.');
        }

        $refund->update([
            'status' => RefundStatus::Approved->value,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $refund->refresh();
    }

    public function canReview(User $user, OrderRefund $refund): bool
    {
        if ($refund->status !== RefundStatus::Requested) {
            return false;
        }

        return match ($user->role) {
            UserRole::ADMINISTRATOR => $refund->requested_by !== $user->id,
            UserRole::SUPER_ADMINISTRATOR => true,
            default => false,
        };
    }

    public function rejectRefund(OrderRefund $refund, User $approver): OrderRefund
    {
        if (!$this->canReview($approver, $refund)) {
            throw new InvalidArgumentException('This user cannot reject the refund request.');
        }

        $refund->update([
            'status' => RefundStatus::Rejected->value,
            'rejected_by' => $approver->id,
            'rejected_at' => now(),
        ]);

        return $refund->refresh();
    }

    public function processRefund(OrderRefund $refund, User $processor): OrderRefund
    {
        return DB::transaction(function () use ($refund, $processor): OrderRefund {
            $lockedRefund = OrderRefund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($lockedRefund->status !== RefundStatus::Approved) {
                throw new InvalidArgumentException('Only approved refunds can be processed.');
            }

            $order = Order::query()->lockForUpdate()->findOrFail($lockedRefund->order_id);
            $order->unsetRelation('payments');
            $received = $order->totalPaid();
            $processed = (string)$order->refunds()
                ->where('status', RefundStatus::Processed->value)
                ->whereKeyNot($lockedRefund->id)
                ->sum('amount');
            $available = bcsub($received, $processed, 2);

            if (bccomp((string)$lockedRefund->amount, $available, 2) === 1) {
                throw new InvalidArgumentException('Processed refunds cannot exceed successfully received payments.');
            }

            $lockedRefund->update([
                'status' => RefundStatus::Processed->value,
                'processed_by' => $processor->id,
                'processed_at' => now(),
            ]);

            return $lockedRefund->refresh();
        });
    }
}
