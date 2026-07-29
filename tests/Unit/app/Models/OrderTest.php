<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if order uses required traits', function () {
    $order = new Order();

    // Check for HasFactory trait
    expect(method_exists($order, 'factory'))->toBeTrue();

    // Check for HasUuids trait
    expect(method_exists($order, 'uniqueIds'))->toBeTrue();

    // Check for HasAttachments trait
    expect(method_exists($order, 'attachments'))->toBeTrue();
});
it('checks if fillable attributes are set correctly', function () {
    $order = new Order();
    $fillable = $order->getFillable();

    $expectedFillable = [
        'customer_id',
        'title',
        'description',
        'status',
        'lifecycle_status',
        'disposition_status',
        'priority',
        'estimated_completion',
        'actual_completion',
        'notes',
        'created_by',
        'updated_by',
        'assigned_to',
    ];

    expect($fillable)->toEqual($expectedFillable);
});
it('checks if casts attributes are set correctly', function () {
    $order = new Order();
    $casts = $order->getCasts();

    expect($casts)->toHaveKey('estimated_completion');
    expect($casts)->toHaveKey('actual_completion');
    $this->assertStringContainsString('datetime', $casts['estimated_completion']);
    $this->assertStringContainsString('datetime', $casts['actual_completion']);
});
it('checks if customer relationship is correct', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $order = Order::factory()->createQuietly(['customer_id' => $customer->id]);

    expect($order->customer)->toBeInstanceOf(User::class);
    expect($order->customer->id)->toEqual($customer->id);
    expect($order->customer->role->value)->toEqual(UserRole::CUSTOMER->value);
});
it('checks if customer relationship returns null for non customers', function () {
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $order = Order::factory()->createQuietly(['customer_id' => $employee->id]);

    expect($order->customer)->toBeNull();
});
it('checks if created by relationship is correct', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly(['created_by' => $user->id]);

    expect($order->createdBy)->toBeInstanceOf(User::class);
    expect($order->createdBy->id)->toEqual($user->id);
});
it('checks if updated by relationship is correct', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly(['updated_by' => $user->id]);

    expect($order->updatedBy)->toBeInstanceOf(User::class);
    expect($order->updatedBy->id)->toEqual($user->id);
});
it('checks if assigned to relationship is correct', function () {
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $order = Order::factory()->createQuietly(['assigned_to' => $employee->id]);

    expect($order->assignedTo)->toBeInstanceOf(User::class);
    expect($order->assignedTo->id)->toEqual($employee->id);
    expect($order->assignedTo->role->value)->toEqual(UserRole::EMPLOYEE->value);
});
it('checks if order histories relationship is correct', function () {
    $order = Order::factory()->createQuietly();

    // Create order histories
    OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open,
        'new_value' => OrderStatus::InProgress,
        'comment' => 'Order started',
        'created_by' => User::factory()->create()->id,
    ]);

    OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::InProgress,
        'new_value' => OrderStatus::ReadyForDelivery,
        'comment' => 'Order ready',
        'created_by' => User::factory()->create()->id,
    ]);

    expect($order->orderHistories)->toHaveCount(2);
    $this->assertContainsOnlyInstancesOf(OrderHistory::class, $order->orderHistories);
});
it('checks if can create an order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly([
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'created_by' => $user->id,
    ]);
});
it('checks if belongs to a creator', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly(['created_by' => $user->id]);

    expect($order->createdBy())->toBeInstanceOf(BelongsTo::class);
    expect($order->createdBy->id)->toEqual($user->id);
});
it('checks if estimated completion date casting is correct', function () {
    $date = now()->addDays(5);
    $order = Order::factory()->createQuietly([
        'estimated_completion' => $date,
    ]);

    expect($order->estimated_completion)->toBeInstanceOf(CarbonImmutable::class);
    expect($order->estimated_completion->format('Y-m-d H:i:s'))->toEqual($date->format('Y-m-d H:i:s'));
});
it('checks if actual completion date casting is correct', function () {
    $date = now()->subDays(2);
    $order = Order::factory()->createQuietly([
        'actual_completion' => $date,
    ]);

    expect($order->actual_completion)->toBeInstanceOf(CarbonImmutable::class);
    expect($order->actual_completion->format('Y-m-d H:i:s'))->toEqual($date->format('Y-m-d H:i:s'));
});
it('checks if actual completion can be null', function () {
    $order = Order::factory()->createQuietly([
        'actual_completion' => null,
    ]);

    expect($order->actual_completion)->toBeNull();
});
it('checks if mass assignment is correct', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $creator = User::factory()->create();

    $data = [
        'customer_id' => $customer->id,
        'title' => 'Test Order',
        'description' => 'Test Description',
        'status' => OrderStatus::Open,
        'priority' => OrderPriority::HIGH,
        'estimated_completion' => now()->addDays(7),
        'notes' => 'Test notes',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
        'assigned_to' => $creator->id,
    ];

    $order = Order::factory()->createQuietly($data);

    expect($order->title)->toEqual($data['title']);
    expect($order->description)->toEqual($data['description']);
    expect($order->status->value)->toEqual($data['status']->value);
    expect($order->priority->value)->toEqual($data['priority']->value);
    expect($order->notes)->toEqual($data['notes']);
});
it('checks if order can be created with minimum fields', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $creator = User::factory()->create();

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'title' => 'Minimal Order',
        'description' => 'Minimal Description',
        'status' => OrderStatus::Open,
        'priority' => OrderPriority::NORMAL,
        'estimated_completion' => now()->addDays(3),
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
        'assigned_to' => $creator->id,
    ]);

    expect($order)->toBeInstanceOf(Order::class);
    expect($order->id)->not->toBeNull();
    expect($order->actual_completion)->toBeNull();
    expect($order->notes)->toBeNull();
    expect($order->assigned_to)->toEqual($creator->id);
});
it('checks if new factory returns correct instance', function () {
    $factory = Order::factory();

    expect($factory)->toBeInstanceOf(OrderFactory::class);
});
it('checks if uuid generation is correct', function () {
    $order = Order::factory()->createQuietly();

    expect($order->uuid)->not->toBeNull();
    expect($order->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});
it('checks if model table name is correct', function () {
    $order = new Order();

    expect($order->getTable())->toEqual('orders');
});
it('checks if all status values are unique', function () {
    $reflection = new ReflectionClass(Order::class);
    $constants = $reflection->getConstants();

    $statusConstants = array_filter($constants, function ($key) {
        return str_starts_with($key, 'STATUS_');
    }, ARRAY_FILTER_USE_KEY);

    $statusValues = array_values($statusConstants);
    $uniqueValues = array_unique($statusValues);

    expect($uniqueValues)->toHaveCount(count($statusValues), 'Status values should be unique');
});
it('checks if all priority values are unique', function () {
    $reflection = new ReflectionClass(Order::class);
    $constants = $reflection->getConstants();

    $priorityConstants = array_filter($constants, function ($key) {
        return str_starts_with($key, 'PRIORITY_');
    }, ARRAY_FILTER_USE_KEY);

    $priorityValues = array_values($priorityConstants);
    $uniqueValues = array_unique($priorityValues);

    expect($uniqueValues)->toHaveCount(count($priorityValues), 'Priority values should be unique');
});
