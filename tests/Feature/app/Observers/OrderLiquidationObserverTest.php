<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderPayment;
use App\Models\OrderService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('keeps service completion and payment totals available from the order', function () {
    $order = Order::factory()->createQuietly();
    OrderMotorInfo::create(['order_id' => $order->id]);

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

    $employee = App\Models\User::factory()->create();
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1500.00,
        'created_by' => $employee->id,
    ]);

    expect($order->fresh()->completedTotal())->toBe('1252.80')
        ->and($order->fresh()->paymentStatus())->toBe('Paid');
});
it('does not store payment state on motor information', function () {
    $order = Order::factory()->createQuietly();
    $info = OrderMotorInfo::create(['order_id' => $order->id]);

    expect($info->fresh()->getAttributes())->not->toHaveKeys(['down_payment', 'total_cost', 'is_fully_paid']);
});
