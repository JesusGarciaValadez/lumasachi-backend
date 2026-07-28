<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Carbon\Carbon;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if can track status changes', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_STATUS,
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Started working on the order',
        'created_by' => $user->id,
    ]);

    expect($history->field_changed)->toEqual(OrderHistory::FIELD_STATUS);
    expect($history->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($history->getRawOriginal('new_value'))->toEqual(OrderStatus::InProgress->value);

    // Test automatic casting
    expect($history->old_value)->toBeInstanceOf(OrderStatus::class);
    expect($history->new_value)->toBeInstanceOf(OrderStatus::class);
    expect($history->old_value)->toEqual(OrderStatus::Open);
    expect($history->new_value)->toEqual(OrderStatus::InProgress);
});
it('checks if can track priority changes', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_PRIORITY,
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::URGENT->value,
        'comment' => 'Client requested urgent delivery',
        'created_by' => $user->id,
    ]);

    expect($history->field_changed)->toEqual(OrderHistory::FIELD_PRIORITY);

    // Test automatic casting
    expect($history->old_value)->toBeInstanceOf(OrderPriority::class);
    expect($history->new_value)->toBeInstanceOf(OrderPriority::class);
    expect($history->old_value)->toEqual(OrderPriority::NORMAL);
    expect($history->new_value)->toEqual(OrderPriority::URGENT);
});
it('checks if can track assignment changes', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();
    $employee1 = User::factory()->create();
    $employee2 = User::factory()->create();

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
        'old_value' => (string)$employee1->id,
        'new_value' => (string)$employee2->id,
        'comment' => 'Reassigned due to workload',
        'created_by' => $user->id,
    ]);

    expect($history->field_changed)->toEqual(OrderHistory::FIELD_ASSIGNED_TO);
    expect($history->old_value)->toEqual((string)$employee1->id);
    expect($history->new_value)->toEqual((string)$employee2->id);
});
it('checks if can track date changes', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();
    $oldDate = Carbon::now()->subDays(5);
    $newDate = Carbon::now()->addDays(2);

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_ESTIMATED_COMPLETION,
        'old_value' => $oldDate->toISOString(),
        'new_value' => $newDate->toISOString(),
        'comment' => 'Delivery delayed due to supplier issue',
        'created_by' => $user->id,
    ]);

    expect($history->field_changed)->toEqual(OrderHistory::FIELD_ESTIMATED_COMPLETION);

    // Test automatic casting to Carbon
    expect($history->old_value)->toBeInstanceOf(Carbon::class);
    expect($history->new_value)->toBeInstanceOf(Carbon::class);
    expect($history->old_value->format('Y-m-d'))->toEqual($oldDate->format('Y-m-d'));
    expect($history->new_value->format('Y-m-d'))->toEqual($newDate->format('Y-m-d'));
});
it('checks if can track text field changes', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_TITLE,
        'old_value' => 'Old Title',
        'new_value' => 'New Updated Title',
        'comment' => 'Title corrected per client request',
        'created_by' => $user->id,
    ]);

    expect($history->field_changed)->toEqual(OrderHistory::FIELD_TITLE);
    expect($history->old_value)->toEqual('Old Title');
    expect($history->new_value)->toEqual('New Updated Title');
});
it('checks if can handle null values', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    // Test setting a value from null
    $history1 = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_NOTES,
        'old_value' => null,
        'new_value' => 'Some new notes',
        'comment' => 'Added notes',
        'created_by' => $user->id,
    ]);

    expect($history1->old_value)->toBeNull();
    expect($history1->new_value)->toEqual('Some new notes');

    // Test setting a value to null
    $history2 = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
        'old_value' => '123',
        'new_value' => null,
        'comment' => 'Unassigned from employee',
        'created_by' => $user->id,
    ]);

    expect($history2->old_value)->toEqual('123');
    expect($history2->new_value)->toBeNull();
});
it('checks if generates human readable descriptions', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    // Test status change description
    $history1 = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_STATUS,
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $user->id,
    ]);

    $expectedDescription = 'Status changed from Open to In Progress';
    expect($history1->description)->toEqual($expectedDescription);

    // Test new value only
    $history2 = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_PRIORITY,
        'old_value' => null,
        'new_value' => OrderPriority::HIGH->value,
        'created_by' => $user->id,
    ]);

    $expectedDescription = 'Priority set to: High';
    expect($history2->description)->toEqual($expectedDescription);

    // Test value removed
    $history3 = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_NOTES,
        'old_value' => 'Some notes',
        'new_value' => null,
        'created_by' => $user->id,
    ]);

    $expectedDescription = 'Notes removed (was: Some notes)';
    expect($history3->description)->toEqual($expectedDescription);
});
it('checks if formats date values in description', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();
    $date = Carbon::now()->addDays(5);

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_ESTIMATED_COMPLETION,
        'old_value' => null,
        'new_value' => $date->toISOString(),
        'created_by' => $user->id,
    ]);

    $expectedDescription = __('orders.history_messages.set', [
        'field' => __('orders.history_messages.fields.estimated_completion'),
        'value' => $date->locale(app()->getLocale())->translatedFormat('M j, Y H:i'),
    ]);
    expect($history->description)->toEqual($expectedDescription);
});
it('checks if maintains backward compatibility with enum serialization', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    $history = new OrderHistory();
    $history->order_id = $order->id;
    $history->field_changed = OrderHistory::FIELD_STATUS;
    $history->created_by = $user->id;

    // Test setting enum directly
    $history->old_value = OrderStatus::Open;
    $history->new_value = OrderStatus::Delivered;

    $history->save();

    // Verify stored as string values
    expect($history->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($history->getRawOriginal('new_value'))->toEqual(OrderStatus::Delivered->value);

    // Verify retrieved as enums
    $freshHistory = OrderHistory::find($history->id);
    expect($freshHistory->old_value)->toBeInstanceOf(OrderStatus::class);
    expect($freshHistory->new_value)->toBeInstanceOf(OrderStatus::class);
});
it('checks if can query history by field', function () {
    $order = Order::factory()->createQuietly();
    $user = User::factory()->create();

    // Create different types of history
    OrderHistory::factory()->count(3)->create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_STATUS,
    ]);

    OrderHistory::factory()->count(2)->create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_PRIORITY,
    ]);

    OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
    ]);

    // Query by field
    $statusChanges = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_STATUS)
        ->get();

    $priorityChanges = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_PRIORITY)
        ->get();

    expect($statusChanges)->toHaveCount(3);
    expect($priorityChanges)->toHaveCount(2);
});
it('checks if factory creates valid history entries', function () {
    $history = OrderHistory::factory()->create();

    expect($history->order_id)->not->toBeNull();
    expect($history->field_changed)->not->toBeNull();
    expect($history->created_by)->not->toBeNull();
    expect([
        OrderHistory::FIELD_STATUS,
        OrderHistory::FIELD_PRIORITY,
        OrderHistory::FIELD_ASSIGNED_TO,
        OrderHistory::FIELD_TITLE,
        OrderHistory::FIELD_ESTIMATED_COMPLETION,
        OrderHistory::FIELD_ACTUAL_COMPLETION,
        OrderHistory::FIELD_NOTES,
    ])->toContain($history->field_changed);
});
it('checks if factory state methods work correctly', function () {
    $order = Order::factory()->createQuietly();

    // Test status change state
    $statusHistory = OrderHistory::factory()
        ->statusChange(OrderStatus::Open, OrderStatus::Delivered)
        ->create(['order_id' => $order->id]);

    expect($statusHistory->field_changed)->toEqual(OrderHistory::FIELD_STATUS);
    expect($statusHistory->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($statusHistory->getRawOriginal('new_value'))->toEqual(OrderStatus::Delivered->value);

    // Test priority change state
    $priorityHistory = OrderHistory::factory()
        ->priorityChange(OrderPriority::LOW, OrderPriority::HIGH)
        ->create(['order_id' => $order->id]);

    expect($priorityHistory->field_changed)->toEqual(OrderHistory::FIELD_PRIORITY);
    expect($priorityHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::LOW->value);
    expect($priorityHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::HIGH->value);

    // Test assignment change state
    $oldAssignee = User::factory()->createQuietly();
    $newAssignee = User::factory()->createQuietly();

    $assignmentHistory = OrderHistory::factory()
        ->assignmentChange($oldAssignee, $newAssignee)
        ->create(['order_id' => $order->id]);

    expect($assignmentHistory->field_changed)->toEqual(OrderHistory::FIELD_ASSIGNED_TO);
    expect($assignmentHistory->getRawOriginal('old_value'))->toEqual($oldAssignee->id);
    expect($assignmentHistory->getRawOriginal('new_value'))->toEqual($newAssignee->id);
});
