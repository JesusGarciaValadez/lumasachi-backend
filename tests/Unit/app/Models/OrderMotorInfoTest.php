<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('calculates payment totals from the append-only ledger', function () {
    $order = Order::factory()->createQuietly();
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => true,
        'net_price' => 1252.80,
    ]);

    $employee = App\Models\User::factory()->create();
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1500.00,
        'created_by' => $employee->id,
    ]);

    expect($order->fresh()->totalPaid())->toBe('1500.00')
        ->and($order->fresh()->paymentStatus())->toBe('Paid')
        ->and($order->fresh()->financialTotals()['remaining_balance'])->toBe('0.00');
});

it('compares payment amounts at currency precision', function () {
    $order = Order::factory()->createQuietly();
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => true,
        'net_price' => 0.30,
    ]);

    $employee = App\Models\User::factory()->create();
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => 0.10,
        'created_by' => $employee->id,
    ]);

    expect($order->fresh()->paymentStatus())->toBe('Partially Paid')
        ->and($order->fresh()->financialTotals()['remaining_balance'])->toBe('0.20');
});
