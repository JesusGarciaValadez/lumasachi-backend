<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderRefund;
use App\Models\User;
use App\Services\OrderRefundService;

final class OrderRefundPolicy
{
    public function __construct(private OrderRefundService $refundService)
    {
    }

    public function approve(User $user, OrderRefund $refund): bool
    {
        return $this->refundService->canReview($user, $refund);
    }

    public function reject(User $user, OrderRefund $refund): bool
    {
        return $this->refundService->canReview($user, $refund);
    }
}
