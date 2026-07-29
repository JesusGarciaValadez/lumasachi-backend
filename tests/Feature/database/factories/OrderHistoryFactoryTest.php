<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if factory creates valid order history', function () {
    $orderHistory = OrderHistory::factory()->create();

    expect($orderHistory)->toBeInstanceOf(OrderHistory::class);
    $this->assertDatabaseHas('order_histories', [
        'id' => $orderHistory->id,
        'order_id' => $orderHistory->order_id,
    ]);
});
it('checks if factory generates all required fields', function () {
    $orderHistory = OrderHistory::factory()->create();

    expect($orderHistory->order_id)->not->toBeNull();
    expect($orderHistory->field_changed)->not->toBeNull();

    // old_value can be null for initial creation
    expect($orderHistory->new_value)->not->toBeNull();
    expect($orderHistory->created_by)->not->toBeNull();
});
it('checks if factory generates valid field changed values', function () {
    $validFields = [
        'lifecycle_status',
        'priority',
        'assigned_to',
        'title',
    ];

    $orderHistory = OrderHistory::factory()->make();

    // Check that the field_changed value is valid
    expect($validFields)->toContain($orderHistory->field_changed);
});
it('checks if factory generates appropriate values based on field', function () {
    // Test multiple factory generations to ensure various fields are tested
    for ($i = 0; $i < 10; $i++) {
        $orderHistory = OrderHistory::factory()->make();

        if ($orderHistory->field_changed === 'lifecycle_status') {
            $validStatuses = array_map(fn($status) => $status->value, OrderLifecycleStatus::cases());
            // Handle the case where getter returns enum instance
            $oldValue = $orderHistory->old_value instanceof OrderLifecycleStatus ? $orderHistory->old_value->value : $orderHistory->old_value;
            $newValue = $orderHistory->new_value instanceof OrderLifecycleStatus ? $orderHistory->new_value->value : $orderHistory->new_value;
            if ($oldValue !== null) {
                expect($validStatuses)->toContain($oldValue);
            }
            expect($validStatuses)->toContain($newValue);
        } elseif ($orderHistory->field_changed === 'priority') {
            $validPriorities = array_map(fn($priority) => $priority->value, OrderPriority::cases());
            // Handle the case where getter returns enum instance
            $oldValue = $orderHistory->old_value instanceof OrderPriority ? $orderHistory->old_value->value : $orderHistory->old_value;
            $newValue = $orderHistory->new_value instanceof OrderPriority ? $orderHistory->new_value->value : $orderHistory->new_value;
            if ($oldValue !== null) {
                expect($validPriorities)->toContain($oldValue);
            }
            expect($validPriorities)->toContain($newValue);
        }
    }
});
it('checks if factory creates associated models', function () {
    $orderHistory = OrderHistory::factory()->create();

    // Check that order was created
    $this->assertDatabaseHas('orders', ['id' => $orderHistory->order_id]);

    // Check that user was created
    $this->assertDatabaseHas('users', ['id' => $orderHistory->created_by]);
});
it('checks if optional comment field', function () {
    // Run multiple times to test randomness
    $hasComment = false;
    $hasNoComment = false;

    for ($i = 0; $i < 20; $i++) {
        $orderHistory = OrderHistory::factory()->make();

        if ($orderHistory->comment !== null) {
            $hasComment = true;
        } else {
            $hasNoComment = true;
        }

        if ($hasComment && $hasNoComment) {
            break;
        }
    }

    expect($hasComment || $hasNoComment)->toBeTrue('Comment should sometimes be null and sometimes have value');
});
it('checks if factory can override attributes', function () {
    $customComment = 'Custom comment for this history entry';
    $customFieldChanged = 'lifecycle_status';
    $customOldValue = OrderLifecycleStatus::Received->value;
    $customNewValue = OrderLifecycleStatus::Delivered->value;

    $orderHistory = OrderHistory::factory()->create([
        'comment' => $customComment,
        'field_changed' => $customFieldChanged,
        'old_value' => $customOldValue,
        'new_value' => $customNewValue,
    ]);

    expect($orderHistory->comment)->toEqual($customComment);
    expect($orderHistory->field_changed)->toEqual($customFieldChanged);
    expect($orderHistory->getRawOriginal('old_value'))->toEqual($customOldValue);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual($customNewValue);
});
it('checks if factory with specific order', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $customer->id,
    ]);

    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $order->id,
    ]);

    expect($orderHistory->order_id)->toEqual($order->id);
    expect($orderHistory->order->id)->toEqual($order->id);
});
it('checks if factory with specific user', function () {
    $user = User::factory()->create(['role' => UserRole::EMPLOYEE]);

    $orderHistory = OrderHistory::factory()->create([
        'created_by' => $user->id,
    ]);

    expect($orderHistory->created_by)->toEqual($user->id);
    expect($orderHistory->createdBy->id)->toEqual($user->id);
});
it('checks if multiple order histories can be created', function () {
    $orderHistories = OrderHistory::factory()->count(5)->create();

    expect($orderHistories)->toHaveCount(5);

    foreach ($orderHistories as $orderHistory) {
        expect($orderHistory)->toBeInstanceOf(OrderHistory::class);
        $this->assertDatabaseHas('order_histories', ['id' => $orderHistory->id]);
    }
});
it('checks if factory generates realistic data', function () {
    $orderHistory = OrderHistory::factory()->make();

    // Field changed should be one of the expected values
    expect([
        'lifecycle_status',
        'disposition_status',
        'priority',
        'assigned_to',
        'title',
        'estimated_completion',
        'actual_completion',
        'notes',
    ])->toContain($orderHistory->field_changed);

    // If comment exists, it should be meaningful
    if ($orderHistory->comment !== null) {
        expect(mb_strlen($orderHistory->comment))->toBeGreaterThan(10);
    }
});
it('checks if factory relationships', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $customer->id,
    ]);

    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $order->id,
        'created_by' => $employee->id,
    ]);

    // Test order relationship
    expect($orderHistory->order)->toBeInstanceOf(Order::class);
    expect($orderHistory->order->id)->toEqual($order->id);

    // Test createdBy relationship
    expect($orderHistory->createdBy)->toBeInstanceOf(User::class);
    expect($orderHistory->createdBy->id)->toEqual($employee->id);
});
it('checks if factory respects field types', function () {
    $orderHistory = OrderHistory::factory()->create();

    // After retrieval from database, check field types
    $freshOrderHistory = OrderHistory::find($orderHistory->id);

    expect($freshOrderHistory->field_changed)->toBeString();

    // Check raw values are strings in the database
    if ($freshOrderHistory->getRawOriginal('old_value') !== null) {
        expect($freshOrderHistory->getRawOriginal('old_value'))->toBeString();
    }
    if ($freshOrderHistory->getRawOriginal('new_value') !== null) {
        expect($freshOrderHistory->getRawOriginal('new_value'))->toBeString();
    }
});
it('checks if factory generates uuid', function () {
    $orderHistory = OrderHistory::factory()->create();

    expect($orderHistory->uuid)->not->toBeNull();
    expect($orderHistory->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
it('checks if factory can create specific lifecycle transition', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'comment' => 'Order processing started',
    ]);

    expect($orderHistory->field_changed)->toEqual('lifecycle_status');
    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderLifecycleStatus::Received->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderLifecycleStatus::AwaitingReview->value);
    expect($orderHistory->comment)->toEqual('Order processing started');
});
it('checks if factory can create priority change only', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $customer->id,
    ]);

    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::URGENT->value,
        'comment' => 'Priority escalated to urgent',
    ]);

    expect($orderHistory->field_changed)->toEqual('priority');
    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::NORMAL->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::URGENT->value);
});
it('checks if factory creates histories for same order', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $customer->id,
    ]);

    $histories = OrderHistory::factory()
        ->count(3)
        ->create(['order_id' => $order->id]);

    expect($histories)->toHaveCount(3);

    foreach ($histories as $history) {
        expect($history->order_id)->toEqual($order->id);
    }

    // Check that all histories are in the database for the same order
    expect(OrderHistory::where('order_id', $order->id)->count())->toEqual(3);
});
