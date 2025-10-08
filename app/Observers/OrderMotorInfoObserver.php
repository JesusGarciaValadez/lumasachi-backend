<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OrderMotorInfo;
use App\Traits\CachesOrders;

final class OrderMotorInfoObserver
{
    use CachesOrders;

    public function created(OrderMotorInfo $info): void
    {
        self::bumpVersion();
    }

    public function updated(OrderMotorInfo $info): void
    {
        // Auto-set is_fully_paid based on totals when down_payment or total_cost changed
        if ($info->wasChanged('down_payment') || $info->wasChanged('total_cost')) {
            $info->is_fully_paid = (float) $info->down_payment >= (float) $info->total_cost;
            // Avoid infinite loop: only save if dirty and not in a save cycle
            if ($info->isDirty('is_fully_paid')) {
                $info->saveQuietly();
            }
        }

        self::bumpVersion();
    }

    public function deleted(OrderMotorInfo $info): void
    {
        self::bumpVersion();
    }

    public function restored(OrderMotorInfo $info): void
    {
        self::bumpVersion();
    }

    public function forceDeleted(OrderMotorInfo $info): void
    {
        self::bumpVersion();
    }
}
