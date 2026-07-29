<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defines only canonical lifecycle statuses', function () {
    expect(OrderLifecycleStatus::getStatuses())->toEqual([
        'Received',
        'Awaiting Review',
        'Reviewed',
        'Awaiting Customer Approval',
        'Ready for Work',
        'Ready for Delivery',
        'Delivered',
    ]);
});

it('provides localized labels for canonical lifecycle statuses', function () {
    expect(OrderLifecycleStatus::Received->getLabel())->toBe('Received')
        ->and(OrderLifecycleStatus::ReadyForDelivery->getLabel())->toBe('Ready for Delivery');
});

it('stores canonical lifecycle values on orders', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'lifecycle_status' => OrderLifecycleStatus::Delivered,
    ]);

    expect($order->lifecycleStatus())->toBe(OrderLifecycleStatus::Delivered);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);
});

it('serializes lifecycle status without a legacy status field', function () {
    $order = Order::factory()->createQuietly([
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork,
    ]);

    expect($order->toArray())
        ->toHaveKey('lifecycle_status', OrderLifecycleStatus::ReadyForWork->value)
        ->not->toHaveKey('status');
});

it('keeps lifecycle values unique', function () {
    $values = OrderLifecycleStatus::getStatuses();

    expect(array_unique($values))->toHaveCount(count($values));
});
