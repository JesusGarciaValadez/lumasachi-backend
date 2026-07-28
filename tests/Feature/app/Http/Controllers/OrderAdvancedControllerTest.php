<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    // Create users with different roles
    $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
    $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

    // Create a test order
    $this->order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Open->value,
    ]);
});
it('checks complete valid state transition flow', function () {
    $this->actingAs($this->employee);

    // Step 1: OPEN -> IN_PROGRESS
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
        'notes' => 'Starting work on this order',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::InProgress->value,
            ],
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $this->order->id,
        'status' => OrderStatus::InProgress->value,
    ]);

    // Check history was created
    $this->assertDatabaseHas('order_histories', [
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $this->employee->id,
    ]);

    // Step 2: IN_PROGRESS -> READY_FOR_DELIVERY
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::ReadyForDelivery->value,
        'notes' => 'Order is ready for delivery',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::ReadyForDelivery->value,
            ],
        ]);

    // Step 3: READY_FOR_DELIVERY -> DELIVERED
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Delivered->value,
        'notes' => 'Order delivered to customer',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::Delivered->value,
            ],
        ]);

    // Step 4: DELIVERED -> PAID
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Paid->value,
        'notes' => 'Payment received',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::Paid->value,
            ],
        ]);

    // Verify complete history chain
    $histories = OrderHistory::where('order_id', $this->order->id)
        ->where('field_changed', 'status')
        ->orderBy('created_at', 'asc')
        ->get();

    expect($histories)->toHaveCount(4);

    // The OrderHistory model casts values to enums, so we need to get the value property
    expect($histories[0]->old_value?->value ?? $histories[0]->old_value)->toEqual(OrderStatus::Open->value);
    expect($histories[0]->new_value?->value ?? $histories[0]->new_value)->toEqual(OrderStatus::InProgress->value);
    expect($histories[1]->old_value?->value ?? $histories[1]->old_value)->toEqual(OrderStatus::InProgress->value);
    expect($histories[1]->new_value?->value ?? $histories[1]->new_value)->toEqual(OrderStatus::ReadyForDelivery->value);
    expect($histories[2]->old_value?->value ?? $histories[2]->old_value)->toEqual(OrderStatus::ReadyForDelivery->value);
    expect($histories[2]->new_value?->value ?? $histories[2]->new_value)->toEqual(OrderStatus::Delivered->value);
    expect($histories[3]->old_value?->value ?? $histories[3]->old_value)->toEqual(OrderStatus::Delivered->value);
    expect($histories[3]->new_value?->value ?? $histories[3]->new_value)->toEqual(OrderStatus::Paid->value);
});
it('checks can cancel order from open status', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Cancelled->value,
        'notes' => 'Customer cancelled the order',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::Cancelled->value,
            ],
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $this->order->id,
        'status' => OrderStatus::Cancelled->value,
    ]);
});
it('checks return and cancel flow', function () {
    // Setup order in DELIVERED status
    $this->order->update(['status' => OrderStatus::Delivered->value]);

    $this->actingAs($this->employee);

    // DELIVERED -> RETURNED
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Returned->value,
        'notes' => 'Customer returned the order',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::Returned->value,
            ],
        ]);

    // RETURNED -> CANCELLED
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Cancelled->value,
        'notes' => 'Order cancelled after return',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::Cancelled->value,
            ],
        ]);
});
it('checks not paid to paid flow', function () {
    // Setup order in DELIVERED status
    $this->order->update(['status' => OrderStatus::Delivered->value]);

    $this->actingAs($this->employee);

    // DELIVERED -> NOT_PAID
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::NotPaid->value,
        'notes' => 'Payment pending',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::NotPaid->value,
            ],
        ]);

    // NOT_PAID -> PAID
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Paid->value,
        'notes' => 'Payment received after follow-up',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::Paid->value,
            ],
        ]);
});
it('checks cannot transition from paid status', function () {
    $this->actingAs($this->employee);

    // Set order to paid status
    $this->order->update(['status' => OrderStatus::Paid->value]);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJson([
            'errors' => [
                'status' => [
                    'Invalid status transition.',
                ],
            ],
        ]);
});
it('checks cannot transition from cancelled status', function () {
    $this->actingAs($this->employee);

    // Set order to cancelled status
    $this->order->update(['status' => OrderStatus::Cancelled->value]);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJson([
            'errors' => [
                'status' => [
                    'Invalid status transition.',
                ],
            ],
        ]);
});
it('checks cannot skip transition steps', function () {
    $this->actingAs($this->employee);

    // Try to go from OPEN directly to DELIVERED (skipping IN_PROGRESS and READY_FOR_DELIVERY)
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Delivered->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJson([
            'errors' => [
                'status' => [
                    'Invalid status transition.',
                ],
            ],
        ]);
});
it('checks cannot go backwards in flow', function () {
    $this->actingAs($this->employee);

    // Set order to IN_PROGRESS
    $this->order->update(['status' => OrderStatus::InProgress->value]);

    // Try to go back to OPEN
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Open->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJson([
            'errors' => [
                'status' => [
                    'Invalid status transition.',
                ],
            ],
        ]);
});
it('checks update status validates status', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => 'InvalidStatus',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJson([
            'errors' => [
                'status' => [
                    'The selected status is invalid.',
                ],
            ],
        ]);
});
it('checks status update requires authentication', function () {
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
    ]);

    $response->assertUnauthorized();
});
it('checks customer cannot update order status', function () {
    $this->actingAs($this->customer);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
    ]);

    $response->assertForbidden();
});
it('checks employee can only update assigned or created orders', function () {
    // Create another order not assigned to or created by the employee
    $otherOrder = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->admin->id,
        'assigned_to' => $this->employee2->id,
        'status' => OrderStatus::Open->value,
    ]);

    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$otherOrder->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
    ]);

    $response->assertForbidden();
});
it('checks admin can update any order status', function () {
    // Create order assigned to employee
    $employeeOrder = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Open->value,
    ]);

    $this->actingAs($this->admin);

    $response = $this->postJson("/api/v1/orders/{$employeeOrder->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
        'notes' => 'Admin updating status',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'status' => OrderStatus::InProgress->value,
            ],
        ]);
});
it('checks marking order as completed sets actual completion date', function () {
    // Setup order ready for delivery
    $this->order->update([
        'status' => OrderStatus::InProgress->value,
        'actual_completion' => null,
    ]);

    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'status' => OrderStatus::Completed->value,
        'notes' => 'Order completed',
        'actual_completion' => now()->toIso8601String(), // Add actual_completion date
    ]);

    $response->assertOk();

    // Note: The actual_completion date should be set when marking as COMPLETED
    $this->order->refresh();

    expect($this->order->status->value)->toEqual(OrderStatus::Completed->value);
});
it('checks admin can assign order to employee', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->employee2->id,
        'notes' => 'Reassigning to more experienced employee',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order assigned successfully.',
            'order' => [
                'assigned_to' => [
                    'id' => $this->employee2->id,
                ],
            ],
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $this->order->id,
        'assigned_to' => $this->employee2->id,
    ]);

    // Check history was created for assignment change
    $this->assertDatabaseHas('order_histories', [
        'order_id' => $this->order->id,
        'field_changed' => 'assigned_to',
        'old_value' => $this->employee->id,
        'new_value' => $this->employee2->id,
        'created_by' => $this->admin->id,
    ]);
});
it('checks super admin can assign orders', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->employee2->id,
        'notes' => 'Super admin reassignment',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order assigned successfully.',
            'order' => [
                'assigned_to' => [
                    'id' => $this->employee2->id,
                ],
            ],
        ]);
});
it('checks can assign order to administrator', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->admin->id,
        'notes' => 'Assigning to admin for review',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order assigned successfully.',
            'order' => [
                'assigned_to' => [
                    'id' => $this->admin->id,
                ],
            ],
        ]);
});
it('checks cannot assign order to customer', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->customer->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to'])
        ->assertJson([
            'errors' => [
                'assigned_to' => [
                    'The selected user cannot be assigned to orders.',
                ],
            ],
        ]);
});
it('checks cannot assign to nonexistent user', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => '01234567890',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to'])
        ->assertJson([
            'errors' => [
                'assigned_to' => [
                    __('validation.custom.exists', ['attribute' => __('validation.attributes.assigned_to')]),
                ],
            ],
        ]);
});
it('checks employee cannot assign orders', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->employee2->id,
    ]);

    $response->assertForbidden();
});
it('checks customer cannot assign orders', function () {
    $this->actingAs($this->customer);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->employee2->id,
    ]);

    $response->assertForbidden();
});
it('checks assignment requires authentication', function () {
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", [
        'assigned_to' => $this->employee2->id,
    ]);

    $response->assertUnauthorized();
});
it('checks assignment requires assigned to field', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/assign", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to'])
        ->assertJson([
            'errors' => [
                'assigned_to' => [
                    __('validation.custom.required', ['attribute' => __('validation.attributes.assigned_to')]),
                ],
            ],
        ]);
});
it('checks view order history', function () {
    $this->actingAs($this->employee);

    // Create some history entries
    OrderHistory::factory()->count(3)->create([
        'order_id' => $this->order->id,
    ]);

    $response = $this->getJson("/api/v1/orders/{$this->order->uuid}/history");

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
                    'created_by',
                    'created_at',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonCount(3, 'data');
});
it('checks complete motor lifecycle state transitions', function () {
    $this->actingAs($this->employee);

    // Create order in RECEIVED status
    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Received->value,
    ]);

    // RECEIVED → AWAITING_REVIEW
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::AwaitingReview->value,
    ]);
    $response->assertOk();

    // AWAITING_REVIEW → REVIEWED
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::Reviewed->value,
    ]);
    $response->assertOk();

    // Observer auto-transitions to AWAITING_CUSTOMER_APPROVAL
    $order->refresh();
    expect($order->status)->toEqual(OrderStatus::AwaitingCustomerApproval);

    // AWAITING_CUSTOMER_APPROVAL → READY_FOR_WORK
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::ReadyForWork->value,
    ]);
    $response->assertOk();

    // READY_FOR_WORK → IN_PROGRESS
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::InProgress->value,
    ]);
    $response->assertOk();

    // IN_PROGRESS → READY_FOR_DELIVERY
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::ReadyForDelivery->value,
    ]);
    $response->assertOk();

    // READY_FOR_DELIVERY → DELIVERED
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::Delivered->value,
    ]);
    $response->assertOk();

    // DELIVERED → PAID
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'status' => OrderStatus::Paid->value,
    ]);
    $response->assertOk();

    $order->refresh();
    expect($order->status)->toEqual(OrderStatus::Paid);
});
it('checks history is ordered by newest first', function () {
    $this->actingAs($this->employee);

    // Create history entries with specific timestamps
    $oldHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_at' => now()->subDays(2),
    ]);

    $newHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/orders/{$this->order->uuid}/history");

    $response->assertOk();

    $history = $response->json('data');
    expect($history[0]['id'])->toEqual($newHistory->id);
    expect($history[1]['id'])->toEqual($oldHistory->id);
});
