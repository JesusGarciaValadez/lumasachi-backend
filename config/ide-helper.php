<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\OrderItemComponent;
use App\Models\OrderMotorInfo;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Models to ignore
    |--------------------------------------------------------------------------
    |
    | These tenant-owned models require an initialized tenant database context.
    | Static IDE metadata generation runs without a request tenant context.
    |
    */
    'ignored_models' => [
        Attachment::class,
        CompanySetting::class,
        Order::class,
        OrderHistory::class,
        OrderItem::class,
        OrderItemComponent::class,
        OrderMotorInfo::class,
        OrderPayment::class,
        OrderRefund::class,
        OrderService::class,
        ServiceCatalog::class,
        User::class,
    ],
];
