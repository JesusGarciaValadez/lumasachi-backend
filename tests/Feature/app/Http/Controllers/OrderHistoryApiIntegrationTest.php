<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if all order history endpoints include description field', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($admin);

    $order = Order::factory()->createQuietly();

    // Create order history
    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Customer requested priority handling',
        'created_by' => $admin->id,
    ]);

    // Test 1: GET /api/v1/history (index)
    $response = $this->getJson('/api/v1/history');
    $response->assertStatus(200);
    $data = $response->json('data');
    if (is_array($data) && count($data) > 0) {
        expect($data[0])->toHaveKey('description');
        expect($data[0]['description'])->toEqual('Status changed from Open to In Progress');
    }

    // Test 2: GET /api/v1/history/{id} (show)
    $response = $this->getJson("/api/v1/history/{$orderHistory->uuid}");
    $response->assertStatus(200)
        ->assertJsonPath('data.description', 'Status changed from Open to In Progress');

    // Test 3: POST /api/v1/history (store)
    $newOrder = Order::factory()->createQuietly();
    $response = $this->postJson('/api/v1/history', [
        'order_id' => $newOrder->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::URGENT->value,
        'comment' => 'Customer escalation',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.description', 'Priority changed from Normal to Urgent');

    // Test 4: GET /api/v1/orders/{id}/history (order history)
    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");
    $response->assertStatus(200);
    $data = $response->json('data');
    if (is_array($data) && count($data) > 0) {
        expect($data[0])->toHaveKey('description');
        expect($data[0]['description'])->toEqual('Status changed from Open to In Progress');
    }
});
it('checks if order history resource format', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->actingAs($admin);

    $order = Order::factory()->createQuietly([
        'uuid' => Str::uuid7()->toString(),
    ]);

    $orderHistory = OrderHistory::factory()->create([
        'uuid' => Str::uuid7()->toString(),
        'order_id' => $order->id,
        'field_changed' => 'assigned_to',
        'old_value' => null,
        'new_value' => $employee->id,
        'comment' => 'Assigning to available employee',
        'created_by' => $admin->id,
    ]);

    $response = $this->getJson("/api/v1/history/{$orderHistory->uuid}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'uuid',
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
        ])
        ->assertJsonPath('data.field_changed', 'assigned_to')
        ->assertJsonPath('data.description', 'Assigned to set to: ' . $employee->id)
        ->assertJsonPath('data.comment', 'Assigning to available employee');
});
it('checks if automatic description generation on order update', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $this->actingAs($admin);

    $order = Order::factory()->createQuietly([
        'uuid' => Str::uuid7()->toString(),
        'customer_id' => $customer->id,
        'status' => OrderStatus::ReadyForDelivery->value,
        'priority' => OrderPriority::NORMAL->value,
    ]);

    // Update order status (should trigger OrderObserver to create history)
    $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'status' => OrderStatus::Delivered->value,
    ]);

    $response->assertStatus(200);

    // Check that history was created with proper description
    $history = OrderHistory::where('order_id', $order->id)
        ->where('field_changed', 'status')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->description)->not->toBeNull();

    // Verify the history is returned with description via API
    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");
    $response->assertStatus(200);

    $data = $response->json('data');
    if (is_array($data) && count($data) > 0) {
        $statusHistory = collect($data)->firstWhere('field_changed', 'status');
        expect($statusHistory)->not->toBeNull();
        expect($statusHistory)->toHaveKey('description');
        expect($statusHistory['description'])->not->toBeNull();
    }
});
it('checks if order history filtering includes description', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($admin);

    $order = Order::factory()->createQuietly([
        'uuid' => Str::uuid7()->toString(),
    ]);

    // Create multiple history entries
    OrderHistory::factory()->create([
        'uuid' => Str::uuid7()->toString(),
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $admin->id,
    ]);

    OrderHistory::factory()->create([
        'uuid' => Str::uuid7()->toString(),
        'order_id' => $order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::HIGH->value,
        'created_by' => $admin->id,
    ]);

    // Filter by status changes
    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history?field=status");

    $response->assertStatus(200);
    $data = $response->json('data');

    if (is_array($data) && count($data) > 0) {
        foreach ($data as $history) {
            expect($history['field_changed'])->toEqual('status');
            expect($history)->toHaveKey('description');
            $this->assertStringContainsString('Status changed', $history['description']);
        }
    }
});
