<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderMotorInfo;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks casts and remaining balance', function () {
    $order = Order::factory()->createQuietly();

    $info = OrderMotorInfo::create([
        'order_id' => $order->id,
        'brand' => 'Nissan',
        'liters' => '2.5',
        'year' => '2018',
        'model' => 'Altima',
        'cylinder_count' => '4',
        'down_payment' => 1500.00,
        'total_cost' => 1252.80,
        'is_fully_paid' => false,
    ]);

    expect((float)$info->down_payment)->toBe(1500.00);
    expect((float)$info->total_cost)->toBe(1252.80);
    expect($info->is_fully_paid)->toBeFalse();

    // remaining_balance = max(0, total_cost - down_payment) => 0
    expect($info->remaining_balance)->toBe(0.0);
    expect($info->hasPendingPayment())->toBeFalse();
});
it('compares payment amounts at currency precision', function () {
    $order = Order::factory()->createQuietly();

    $info = OrderMotorInfo::create([
        'order_id' => $order->id,
        'down_payment' => 0.10,
        'total_cost' => 0.30,
        'is_fully_paid' => false,
    ]);

    expect($info->hasPendingPayment())->toBeTrue();
    expect($info->remaining_balance)->toBe(0.20);
});
