<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if tracks status changes when updating order', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    Sanctum::actingAs($user);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'created_by' => $user->id,
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    $response->assertOk();

    // Check that history was created
    $history = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_LIFECYCLE_STATUS)
        ->first();

    expect($history)->not->toBeNull();
    expect($history->getRawOriginal('old_value'))->toEqual(OrderLifecycleStatus::Received->value);
    expect($history->getRawOriginal('new_value'))->toEqual(OrderLifecycleStatus::AwaitingReview->value);
    expect($history->created_by)->toEqual($user->id);
});
it('checks if tracks priority changes when updating order', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    Sanctum::actingAs($user);

    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'priority' => OrderPriority::NORMAL->value,
        'created_by' => $user->id,
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'priority' => OrderPriority::URGENT->value,
    ]);

    $response->assertOk();

    // Check that history was created
    $history = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_PRIORITY)
        ->first();

    expect($history)->not->toBeNull();
    expect($history->getRawOriginal('old_value'))->toEqual(OrderPriority::NORMAL->value);
    expect($history->getRawOriginal('new_value'))->toEqual(OrderPriority::URGENT->value);
    expect($history->created_by)->toEqual($user->id);
});
it('checks if tracks multiple field changes in single update', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($user);

    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
        'priority' => OrderPriority::LOW->value,
        'title' => 'Original Title',
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
        'priority' => OrderPriority::HIGH->value,
        'title' => 'Updated Title',
    ]);

    $response->assertOk();

    // Check that multiple history entries were created
    $histories = OrderHistory::where('order_id', $order->id)->get();

    // Should have 3 history entries (status, priority, title)
    expect($histories)->toHaveCount(3);
    expect($histories->pluck('field_changed')->toArray())->toEqualCanonicalizing([
        OrderHistory::FIELD_LIFECYCLE_STATUS,
        OrderHistory::FIELD_PRIORITY,
        OrderHistory::FIELD_TITLE,
    ]);

    // Verify each field change
    $statusHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_LIFECYCLE_STATUS);
    expect($statusHistory)->not->toBeNull();
    expect($statusHistory->getRawOriginal('old_value'))->toEqual(OrderLifecycleStatus::ReadyForDelivery->value);
    expect($statusHistory->getRawOriginal('new_value'))->toEqual(OrderLifecycleStatus::Delivered->value);

    $priorityHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_PRIORITY);
    expect($priorityHistory)->not->toBeNull();
    expect($priorityHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::LOW->value);
    expect($priorityHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::HIGH->value);

    $titleHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_TITLE);
    expect($titleHistory)->not->toBeNull();
    expect($titleHistory->old_value)->toEqual('Original Title');
    expect($titleHistory->new_value)->toEqual('Updated Title');
});
it('checks if tracks assignment changes', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $employee = User::factory()->create();
    Sanctum::actingAs($user);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'assigned_to' => $user->id,
        'created_by' => $user->id,
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'assigned_to' => $employee->id,
    ]);

    $response->assertOk();

    // Check that history was created
    $history = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_ASSIGNED_TO)
        ->first();

    expect($history)->not->toBeNull();
    expect($history->old_value)->toEqual($user->id);
    expect($history->new_value)->toEqual($employee->id);
});
it('checks if tracks estimated completion date changes', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    Sanctum::actingAs($user);

    $oldDate = Carbon::now()->addDays(5);
    $newDate = Carbon::now()->addDays(10);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'estimated_completion' => $oldDate,
        'created_by' => $user->id,
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'estimated_completion' => $newDate->toISOString(),
    ]);

    $response->assertOk();

    // Check that history was created
    $history = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_ESTIMATED_COMPLETION)
        ->first();

    expect($history)->not->toBeNull();

    // Compare dates (ignoring microseconds)
    $oldHistoryDate = Carbon::parse($history->getRawOriginal('old_value'));
    $newHistoryDate = Carbon::parse($history->getRawOriginal('new_value'));

    expect($oldHistoryDate->format('Y-m-d H:i:s'))->toEqual($oldDate->format('Y-m-d H:i:s'));
    expect($newHistoryDate->format('Y-m-d H:i:s'))->toEqual($newDate->format('Y-m-d H:i:s'));
});
it('checks if does not create history when no changes made', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    Sanctum::actingAs($user);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'priority' => OrderPriority::NORMAL->value,
        'title' => 'Test Order',
    ]);

    // Count existing histories
    $initialHistoryCount = OrderHistory::where('order_id', $order->id)->count();

    // Update with same values
    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'priority' => OrderPriority::NORMAL->value,
        'title' => 'Test Order',
    ]);

    $response->assertOk();

    // Verify no new history entries were created
    $newHistoryCount = OrderHistory::where('order_id', $order->id)->count();
    expect($newHistoryCount)->toEqual($initialHistoryCount);
});
it('checks if tracks setting field to null', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    Sanctum::actingAs($user);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'notes' => 'Some important notes',
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'notes' => null,
    ]);

    $response->assertOk();

    // Check notes removal history
    $notesHistory = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_NOTES)
        ->first();

    expect($notesHistory)->not->toBeNull();
    expect($notesHistory->old_value)->toEqual('Some important notes');
    expect($notesHistory->new_value)->toBeNull();
});
it('checks if assigned to field cannot be set to null', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($user);

    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly([
        'assigned_to' => User::factory()->create()->id,
    ]);

    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'assigned_to' => null,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to']);
});
it('checks if order history index returns paginated results', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($user);

    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
    ]);

    // Create multiple history entries
    OrderHistory::factory()->count(25)->create([
        'order_id' => $order->id,
    ]);

    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_id',
                    'field_changed',
                    'old_value',
                    'new_value',
                    'comment',
                    'description',
                    'created_by',
                    'created_at',
                    'creator' => [
                        'id',
                        'full_name',
                        'email',
                    ],
                ],
            ],
            'links',
            'meta',
        ]);

    // Check pagination
    expect($response->json('data'))->toHaveCount(15);
    // Default pagination
    expect($response->json('meta.total'))->toEqual(25);
});
it('checks if order history index filters by field', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($user);

    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
    ]);

    // Create different types of history
    OrderHistory::factory()->count(5)->create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
    ]);

    OrderHistory::factory()->count(3)->create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_PRIORITY,
    ]);

    // Filter by status field
    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history?field=" . OrderHistory::FIELD_LIFECYCLE_STATUS);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);

    // Verify all results are status changes
    foreach ($response->json('data') as $history) {
        expect($history['field_changed'])->toEqual(OrderHistory::FIELD_LIFECYCLE_STATUS);
    }
});
it('checks if order history shows human readable descriptions', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($user);

    $order = Order::factory()->createQuietly([
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
    ]);

    // Create a status change
    $this->putJson("/api/v1/orders/{$order->uuid}", [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);

    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");

    $response->assertOk();

    $history = $response->json('data.0');
    expect($history['description'])->not->toBeNull();
    $this->assertStringContainsString('Lifecycle status changed from', $history['description']);
    $this->assertStringContainsString('Ready for Delivery', $history['description']);
    $this->assertStringContainsString('Delivered', $history['description']);
});
it('returns history only for the requested order', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($user);

    $requestedOrder = Order::factory()->createQuietly();
    $otherOrder = Order::factory()->createQuietly();
    $requestedHistory = OrderHistory::factory()->create([
        'order_id' => $requestedOrder->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
    ]);
    OrderHistory::factory()->create([
        'order_id' => $otherOrder->id,
        'field_changed' => OrderHistory::FIELD_LIFECYCLE_STATUS,
    ]);

    $response = $this->getJson("/api/v1/orders/{$requestedOrder->uuid}/history");

    $response->assertOk();
    $history = $response->json('data');

    expect($history)->toHaveCount(1);
    expect($history[0]['uuid'])->toBe($requestedHistory->uuid);
});
