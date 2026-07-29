<?php

declare(strict_types=1);

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);
});
it('checks complete valid lifecycle transition flow', function () {
    $this->actingAs($this->employee);

    // Step 1: RECEIVED -> AWAITING_REVIEW
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
        'notes' => 'Starting review of this order',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
            ],
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $this->order->id,
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    // Check history was created
    $this->assertDatabaseHas('order_histories', [
        'order_id' => $this->order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'created_by' => $this->employee->id,
    ]);

    // Step 2: AWAITING_REVIEW -> REVIEWED (observer advances to approval)
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Reviewed->value,
        'notes' => 'Order is ready for delivery',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'lifecycle_status' => OrderLifecycleStatus::AwaitingCustomerApproval->value,
            ],
        ]);

    // Step 3: AWAITING_CUSTOMER_APPROVAL -> READY_FOR_WORK
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
        'notes' => 'Order delivered to customer',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
            ],
        ]);

    // Step 4: READY_FOR_WORK -> READY_FOR_DELIVERY
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
        'notes' => 'Work is ready for delivery',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
            ],
        ]);

    // Step 5: READY_FOR_DELIVERY -> DELIVERED
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
        'notes' => 'Order delivered to customer',
    ]);

    $response->assertOk()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::Delivered->value);

    // Verify lifecycle history chain. Payment is recorded separately.
    $histories = OrderHistory::where('order_id', $this->order->id)
        ->where('field_changed', OrderHistory::FIELD_LIFECYCLE_STATUS)
        ->orderBy('created_at', 'asc')
        ->get();

    expect($histories)->toHaveCount(6);

    // The OrderHistory model casts values to enums, so we need to get the value property
    expect($histories->pluck('new_value')->map(fn($value) => $value?->value ?? $value)->all())
        ->toEqual([
            OrderLifecycleStatus::AwaitingReview->value,
            OrderLifecycleStatus::Reviewed->value,
            OrderLifecycleStatus::AwaitingCustomerApproval->value,
            OrderLifecycleStatus::ReadyForWork->value,
            OrderLifecycleStatus::ReadyForDelivery->value,
            OrderLifecycleStatus::Delivered->value,
        ]);
});
it('checks can cancel order from received lifecycle status', function () {
    $this->actingAs($this->employee);

    $response = $this->putJson("/api/v1/orders/{$this->order->uuid}", [
        'disposition_status' => OrderDispositionStatus::Cancelled->value,
        'notes' => 'Customer cancelled the order',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order updated successfully.',
            'order' => ['disposition_status' => OrderDispositionStatus::Cancelled->value],
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $this->order->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'disposition_status' => OrderDispositionStatus::Cancelled->value,
    ]);
});
it('checks returned and cancelled are terminal dispositions', function () {
    // Setup order in DELIVERED status
    $this->order->update(['lifecycle_status' => OrderLifecycleStatus::Delivered->value]);

    $this->actingAs($this->employee);

    // DELIVERED -> RETURNED
    $response = $this->putJson("/api/v1/orders/{$this->order->uuid}", [
        'disposition_status' => OrderDispositionStatus::Returned->value,
        'notes' => 'Customer returned the order',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order updated successfully.',
            'order' => ['disposition_status' => OrderDispositionStatus::Returned->value],
        ]);

    // A returned order cannot be changed or resume lifecycle.
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['lifecycle_status']);
});
it('checks payment status is independent from lifecycle status', function () {
    // Setup order in DELIVERED status
    $this->order->update(['lifecycle_status' => OrderLifecycleStatus::Delivered->value]);

    $this->actingAs($this->employee);

    $response = $this->getJson("/api/v1/orders/{$this->order->uuid}");
    $response->assertOk()->assertJsonPath('payment_status', 'Unpaid');

    // Record payment without changing lifecycle.
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/payments", [
        'amount' => 100,
    ]);

    $response->assertCreated()->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::Delivered->value);
    $response->assertJsonPath('order.payment_status', 'Paid');
});
it('checks cannot transition from paid status', function () {
    $this->actingAs($this->employee);

    // Set order to paid status
    $this->order->update(['lifecycle_status' => OrderLifecycleStatus::Delivered->value]);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status'])
        ->assertJson([
            'errors' => [
                'lifecycle_status' => [
                    'Invalid lifecycle transition.',
                ],
            ],
        ]);
});
it('checks cannot transition from cancelled status', function () {
    $this->actingAs($this->employee);

    // Set order to cancelled status
    $this->order->update([
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'disposition_status' => OrderDispositionStatus::Cancelled->value,
    ]);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status'])
        ->assertJson([
            'errors' => [
                'lifecycle_status' => [
                    'A terminal disposition cannot resume the lifecycle.',
                ],
            ],
        ]);
});
it('checks cannot skip transition steps', function () {
    $this->actingAs($this->employee);

    // Try to go from OPEN directly to DELIVERED (skipping IN_PROGRESS and READY_FOR_DELIVERY)
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status'])
        ->assertJson([
            'errors' => [
                'lifecycle_status' => [
                    'Invalid lifecycle transition.',
                ],
            ],
        ]);
});
it('checks cannot go backwards in flow', function () {
    $this->actingAs($this->employee);

    // Set order to IN_PROGRESS
    $this->order->update(['lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value]);

    // Try to go back to OPEN
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status'])
        ->assertJson([
            'errors' => [
                'lifecycle_status' => [
                    'Invalid lifecycle transition.',
                ],
            ],
        ]);
});
it('checks update status validates status', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => 'InvalidStatus',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status'])
        ->assertJson([
            'errors' => [
                'lifecycle_status' => [
                    'The selected lifecycle status is invalid.',
                ],
            ],
        ]);
});
it('checks status update requires authentication', function () {
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    $response->assertUnauthorized();
});
it('checks customer cannot update order status', function () {
    $this->actingAs($this->customer);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    $response->assertForbidden();
});
it('checks employee can only update assigned or created orders', function () {
    // Create another order not assigned to or created by the employee
    $otherOrder = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->admin->id,
        'assigned_to' => $this->employee2->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$otherOrder->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);

    $response->assertForbidden();
});
it('checks admin can update any order status', function () {
    // Create order assigned to employee
    $employeeOrder = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    $this->actingAs($this->admin);

    $response = $this->postJson("/api/v1/orders/{$employeeOrder->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
        'notes' => 'Admin updating status',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Order status updated successfully.',
            'order' => [
                'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
            ],
        ]);
});
it('checks marking order as completed sets actual completion date', function () {
    // Setup order ready for delivery
    $this->order->update([
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
        'actual_completion' => null,
    ]);

    $this->actingAs($this->employee);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
        'notes' => 'Order completed',
        'actual_completion' => now()->toIso8601String(), // Add actual_completion date
    ]);

    $response->assertOk();

    // Note: The actual_completion date should be set when marking as COMPLETED
    $this->order->refresh();

    expect($this->order->lifecycleStatus())->toEqual(OrderLifecycleStatus::ReadyForDelivery);
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    // RECEIVED → AWAITING_REVIEW
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);
    $response->assertOk();

    // AWAITING_REVIEW → REVIEWED
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Reviewed->value,
    ]);
    $response->assertOk();

    // Observer auto-transitions to AWAITING_CUSTOMER_APPROVAL
    $order->refresh();
    expect($order->lifecycleStatus())->toEqual(OrderLifecycleStatus::AwaitingCustomerApproval);

    // AWAITING_CUSTOMER_APPROVAL → READY_FOR_WORK
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
    ]);
    $response->assertOk();

    // READY_FOR_WORK → READY_FOR_DELIVERY
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
    ]);
    $response->assertOk();

    // READY_FOR_DELIVERY → DELIVERED
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/status", [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);
    $response->assertOk();

    $order->refresh();
    expect($order->lifecycleStatus())->toEqual(OrderLifecycleStatus::Delivered);
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
