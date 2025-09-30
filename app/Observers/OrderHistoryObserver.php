<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OrderHistory;
use App\Traits\CachesOrderHistories;

final class OrderHistoryObserver
{
    use CachesOrderHistories;

    public function created(OrderHistory $orderHistory): void
    {
        self::bumpVersion();
    }

    public function updated(OrderHistory $orderHistory): void
    {
        self::bumpVersion();
    }

    public function deleted(OrderHistory $orderHistory): void
    {
        self::bumpVersion();
    }

    public function restored(OrderHistory $orderHistory): void
    {
        self::bumpVersion();
    }

    public function forceDeleted(OrderHistory $orderHistory): void
    {
        self::bumpVersion();
    }
}
