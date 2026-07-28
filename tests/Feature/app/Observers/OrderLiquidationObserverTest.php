<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('recalculates total cost on service completion and sets full payment', function () {
    $order = Order::factory()->createQuietly();
    $info = OrderMotorInfo::create([
        'order_id' => $order->id,
        'down_payment' => 1500.00,
        'total_cost' => 0,
        'is_fully_paid' => false,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);

    // Two services, completed, with net prices
    $s1 = OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => false,
        'net_price' => 600.40,
    ]);
    $s2 = OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => false,
        'net_price' => 652.40,
    ]);

    // Mark them completed → triggers recalc
    $s1->update(['is_completed' => true]);
    $s2->update(['is_completed' => true]);

    $info->refresh();
    expect((float)$info->total_cost)->toBe(1252.8);
    expect($info->is_fully_paid)->toBeTrue();
    // 1500 >= 1252.8
});
it('updates is fully paid when down payment changes', function () {
    $order = Order::factory()->createQuietly();
    $info = OrderMotorInfo::create([
        'order_id' => $order->id,
        'down_payment' => 100.00,
        'total_cost' => 200.00,
        'is_fully_paid' => false,
    ]);

    // Increase down payment so it covers total cost
    $info->update(['down_payment' => 250.00]);

    expect($info->fresh()->is_fully_paid)->toBeTrue();
});
