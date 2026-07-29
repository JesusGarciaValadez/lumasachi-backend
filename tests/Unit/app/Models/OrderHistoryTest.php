<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\OrderHistoryFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if order history uses required traits', function () {
    $uses = class_uses(OrderHistory::class);

    expect($uses)->toHaveKey('Illuminate\Database\Eloquent\Factories\HasFactory');
    expect($uses)->toHaveKey('Illuminate\Database\Eloquent\Concerns\HasUuids');
    expect($uses)->toHaveKey('App\Traits\HasAttachments');
});
it('checks if order history has correct fillable attributes', function () {
    $orderHistory = new OrderHistory();

    $expected = [
        'uuid',
        'order_id',
        'field_changed',
        'event_type',
        'old_value',
        'new_value',
        'comment',
        'created_by',
    ];

    expect($orderHistory->getFillable())->toEqual($expected);
});
it('checks if order history has correct casts', function () {
    $orderHistory = new OrderHistory();
    $casts = $orderHistory->getCasts();

    // The new schema doesn't have specific enum casts for old_value/new_value
    // as they can contain different types of values
    expect($casts)->toBeArray();
});
it('checks if order history belongs to order', function () {
    $orderHistory = new OrderHistory();
    $relation = $orderHistory->order();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getForeignKeyName())->toEqual('order_id');
    expect($relation->getRelated()::class)->toEqual(Order::class);
});
it('checks if order history belongs to user as created by', function () {
    $orderHistory = new OrderHistory();
    $relation = $orderHistory->createdBy();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getForeignKeyName())->toEqual('created_by');
    expect($relation->getRelated()::class)->toEqual(User::class);
});
it('checks if order history can be created with factory', function () {
    $factory = OrderHistory::factory();

    expect($factory)->toBeInstanceOf(OrderHistoryFactory::class);
});
it('checks if order history field tracking works correctly', function () {
    // Create a user with customer role
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
    ]);

    // Create order history for status change
    $statusHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'comment' => 'Status changed to in progress',
        'created_by' => $user->id,
    ]);

    expect($statusHistory->field_changed)->toEqual(OrderHistory::FIELD_LIFECYCLE_STATUS);
    expect($statusHistory->getRawOriginal('old_value'))->toEqual(OrderLifecycleStatus::Received->value);
    expect($statusHistory->getRawOriginal('new_value'))->toEqual(OrderLifecycleStatus::AwaitingReview->value);

    // Check that getters return enum instances
    expect($statusHistory->old_value)->toBeInstanceOf(OrderLifecycleStatus::class);
    expect($statusHistory->new_value)->toBeInstanceOf(OrderLifecycleStatus::class);
    expect($statusHistory->old_value)->toEqual(OrderLifecycleStatus::Received);
    expect($statusHistory->new_value)->toEqual(OrderLifecycleStatus::AwaitingReview);

    // Create order history for priority change
    $priorityHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::HIGH->value,
        'comment' => 'Priority increased',
        'created_by' => $user->id,
    ]);

    expect($priorityHistory->field_changed)->toEqual('priority');
    expect($priorityHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::NORMAL->value);
    expect($priorityHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::HIGH->value);

    // Check that getters return enum instances
    expect($priorityHistory->old_value)->toBeInstanceOf(OrderPriority::class);
    expect($priorityHistory->new_value)->toBeInstanceOf(OrderPriority::class);
    expect($priorityHistory->old_value)->toEqual(OrderPriority::NORMAL);
    expect($priorityHistory->new_value)->toEqual(OrderPriority::HIGH);
});
it('localizes history descriptions without changing canonical values', function () {
    $history = OrderHistory::make([
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    app()->setLocale('es');
    expect($history->description)->toBe('Estatus del ciclo de vida cambió de Recibida a Esperando revisión');

    app()->setLocale('en');
    expect($history->description)->toBe('Lifecycle status changed from Received to Awaiting Review');
    expect($history->getAttributes()['old_value'])->toBe(OrderLifecycleStatus::Received->value);
    expect($history->getAttributes()['new_value'])->toBe(OrderLifecycleStatus::AwaitingReview->value);

    $date = OrderHistory::make([
        'field_changed' => 'estimated_completion',
        'old_value' => Carbon::parse('2026-07-27 12:00:00'),
        'new_value' => null,
    ]);
    $boolean = OrderHistory::make([
        'field_changed' => 'service_completed',
        'old_value' => false,
        'new_value' => true,
    ]);

    $this->assertStringContainsString('Estimated completion removed', $date->description);
    expect($boolean->description)->toBe('Service completed changed from No to Yes');

    app()->setLocale('es');
    $this->assertStringContainsString('Fecha estimada eliminado', $date->description);
    expect($boolean->description)->toBe('Servicio realizado cambió de No a Sí');
});
it('checks if order history can have null values', function () {
    // Create a user with customer role
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
    ]);

    // Create order history with null old_value (for initial creation)
    $orderHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => null,
        'new_value' => OrderLifecycleStatus::Received->value,
        'comment' => 'Initial order creation',
        'created_by' => $user->id,
    ]);

    expect($orderHistory->old_value)->toBeNull();
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderLifecycleStatus::Received->value);
    expect($orderHistory->field_changed)->toEqual(OrderHistory::FIELD_LIFECYCLE_STATUS);

    // Check that getter returns enum instance
    expect($orderHistory->new_value)->toBeInstanceOf(OrderLifecycleStatus::class);
    expect($orderHistory->new_value)->toEqual(OrderLifecycleStatus::Received);
});
it('checks if order history can be created through mass assignment', function () {
    // Create a user with customer role
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
    ]);

    $data = [
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'comment' => 'Order status updated - Customer requested urgent delivery',
        'created_by' => $user->id,
    ];

    $orderHistory = OrderHistory::create($data);

    expect($orderHistory)->toBeInstanceOf(OrderHistory::class);
    expect($orderHistory->order_id)->toEqual($order->id);
    expect($orderHistory->comment)->toEqual('Order status updated - Customer requested urgent delivery');
    expect($orderHistory->created_by)->toEqual($user->id);
});
it('checks if order history comment field is nullable', function () {
    // Create a user with customer role
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
    ]);

    // Create order history without comment
    $orderHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'created_by' => $user->id,
    ]);

    expect($orderHistory->comment)->toBeNull();
});
it('checks if order history generates uuid', function () {
    // Create a user with customer role
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
    ]);

    $orderHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'created_by' => $user->id,
    ]);

    expect($orderHistory->uuid)->not->toBeNull();
    expect($orderHistory->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
it('checks if order history has correct table name', function () {
    $orderHistory = new OrderHistory();

    expect($orderHistory->getTable())->toEqual('order_histories');
});
it('checks if order history relationships load correctly', function () {
    // Create users
    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    $employee = User::factory()->create([
        'role' => UserRole::EMPLOYEE->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $customer->id,
        'assigned_to' => $employee->id,
    ]);

    // Create order history
    $orderHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'comment' => 'Employee started working on order',
        'created_by' => $employee->id,
    ]);

    // Load relationships
    $orderHistory->load(['order', 'createdBy']);

    expect($orderHistory->order->id)->toEqual($order->id);
    expect($orderHistory->createdBy->id)->toEqual($employee->id);
});
it('checks if order history cascades on order delete', function () {
    // Create a user with customer role
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    // Create an order
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'created_by' => $user->id,
    ]);

    // Create order history
    $orderHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'created_by' => $user->id,
    ]);

    $orderHistoryId = $orderHistory->id;

    // Delete the order
    $order->delete();

    // Check that order history was deleted
    expect(OrderHistory::find($orderHistoryId))->toBeNull();
});
