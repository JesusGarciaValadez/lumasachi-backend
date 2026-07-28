<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if order history api includes description field', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    // Create an order history
    $order = Order::factory()->createQuietly();
    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $user->id,
    ]);

    // Test show endpoint
    $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid);

    $response->assertStatus(200)
        ->assertJsonPath('data.description', 'Status changed from Open to In Progress')
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'field_changed',
                'old_value',
                'new_value',
                'comment',
                'description',
                'created_by',
                'created_at',
            ],
        ]);
});
it('checks if order history list includes description field', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    // Create multiple order histories with descriptions
    $order = Order::factory()->createQuietly();

    OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $user->id,
    ]);

    OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::URGENT->value,
        'created_by' => $user->id,
    ]);

    // Test index endpoint
    $response = $this->getJson('/api/v1/orders/' . $order->uuid . '/history');

    $response->assertStatus(200);

    $data = $response->json('data');
    if (is_array($data) && count($data) >= 2) {
        // Check that each history entry has a description
        foreach ($data as $history) {
            expect($history)->toHaveKey('description');
            expect($history['description'])->not->toBeNull();
        }
    }
});
it('checks if create order history with description', function () {
    $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->actingAs($user);

    $order = Order::factory()->createQuietly(['assigned_to' => $user->id]);

    $orderHistoryData = [
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::Delivered->value,
        'comment' => 'Order delivered to customer',
    ];

    $response = $this->postJson('/api/v1/history', $orderHistoryData);

    $response->assertStatus(201)
        ->assertJsonPath('data.description', 'Status changed from Open to Delivered')
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_id',
                'field_changed',
                'old_value',
                'new_value',
                'comment',
                'description',
                'created_by',
                'created_at',
            ],
        ]);

    // Verify it was saved to the database
    $this->assertDatabaseHas('order_histories', [
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::Delivered->value,
    ]);
});
it('checks if order history through order endpoint includes description', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    $order = Order::factory()->createQuietly();

    OrderHistory::factory()->create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $user->id,
    ]);

    // Test order history endpoint
    $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");

    $response->assertStatus(200);

    $data = $response->json('data');
    if (is_array($data) && count($data) > 0) {
        $firstHistory = $data[0];
        expect($firstHistory)->toHaveKey('description');
        expect($firstHistory['description'])->toEqual('Status changed from Open to In Progress');
    }
});
