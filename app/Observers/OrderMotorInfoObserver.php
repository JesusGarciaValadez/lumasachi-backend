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
