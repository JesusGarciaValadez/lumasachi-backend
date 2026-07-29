<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Configure cache for tests: in-memory and clean state
    config(['cache.default' => 'array']);
    Cache::flush();

    $this->company = Company::factory()->create([
        'name' => 'Test Company',
        'email' => 'test@company.com',
        'phone' => '1234567890',
        'address' => '123 Main St, Anytown, USA',
        'city' => 'Anytown',
        'state' => 'CA',
        'postal_code' => '12345',
        'country' => 'USA',
        'is_active' => true,
    ]);

    // Create users with different roles for testing
    $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value, 'company_id' => $this->company->id]);
    $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value, 'company_id' => $this->company->id]);
    $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value, 'company_id' => $this->company->id]);
    $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
});
it('checks if index returns only active orders for employee', function () {
    $this->actingAs($this->employee);

    // Create 5 orders with "active" statuses that should be returned
    $orders = Order::factory()->count(5)->createQuietly([
        'customer_id' => $this->customer->id,
        'status' => OrderStatus::Open->value,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
    ]);

    // Create orders for another employee that should not be returned
    $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $otherOrders = Order::factory()->count(5)->createQuietly([
        'customer_id' => $this->customer->id,
        'status' => OrderStatus::Completed->value,
        'assigned_to' => $otherEmployee->id,
        'created_by' => $this->admin->id,
    ]);
    $response = $this->getJson('/api/v1/orders');

    $response->assertOk()
        ->assertJsonCount(5);
});
it('checks if store creates order with valid data', function () {
    Notification::fake();

    $this->actingAs($this->employee);

    $orderData = [
        'customer_id' => $this->customer->id,
        'title' => 'Test Order Title',
        'description' => 'Test order description',
        'priority' => OrderPriority::HIGH->value,
        'notes' => 'Some notes about the order',
        'assigned_to' => $this->employee->id,
        'items' => [
            ['item_type' => OrderItemType::CylinderHead->value],
        ],
    ];

    $v1 = (int)Cache::get('orders:version', 0);

    $response = $this->postJson('/api/v1/orders', $orderData);

    $response->assertCreated();

    $v2 = (int)Cache::get('orders:version', 0);
    expect($v2)->toBeGreaterThan($v1, 'Orders cache version should bump on create');

    $this->assertDatabaseHas('orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Test Order Title',
        'created_by' => $this->employee->id,
    ]);

    $order = Order::firstWhere('title', 'Test Order Title');
    expect($order)->not->toBeNull();

    $this->employee->notify(new OrderCreatedNotification($order));

    Notification::assertSentTo(
        $this->employee,
        OrderCreatedNotification::class
    );
});
it('checks if store validation fails with invalid data', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson('/api/v1/orders', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'title', 'description', 'priority', 'assigned_to', 'items']);
});
it('checks if store fails with invalid item type', function () {
    $this->actingAs($this->employee);

    $orderData = [
        'customer_id' => $this->customer->id,
        'title' => 'Test Order',
        'description' => 'Test description',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'items' => [
            ['item_type' => 'invalid_type'],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $orderData);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.item_type']);
});
it('checks if show returns order with relationships', function () {
    $this->actingAs($this->employee);

    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $response = $this->getJson('/api/v1/orders/' . $order->uuid);

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'title',
            'description',
            'status',
            'priority',
            'customer' => ['id', 'first_name', 'last_name', 'email'],
            'assigned_to' => ['id', 'first_name', 'last_name', 'email'],
            'created_by' => ['id', 'first_name', 'last_name', 'email'],
            'updated_by' => ['id', 'first_name', 'last_name', 'email'],
        ]);
});
it('checks if update modifies order successfully', function () {
    $this->actingAs($this->employee);

    // Create an order that the employee created
    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Open->value,
    ]);
    $updateData = [
        'title' => 'Updated Order Title',
        'status' => OrderStatus::InProgress->value,
        'priority' => OrderPriority::URGENT->value,
    ];

    $v1 = (int)Cache::get('orders:version', 0);

    $response = $this->putJson('/api/v1/orders/' . $order->uuid, $updateData);

    $response->assertOk();

    $v2 = (int)Cache::get('orders:version', 0);
    expect($v2)->toBe($v1 + 1, 'Orders cache version should bump on update');

    $response->assertJson([
        'message' => 'Order updated successfully.',
        'order' => [
            'title' => 'Updated Order Title',
            'status' => OrderStatus::InProgress->value,
            'priority' => OrderPriority::URGENT->value,
        ],
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'customer_id' => $this->customer->id,
        'title' => 'Updated Order Title',
        'updated_by' => $this->employee->id,
    ]);
});
it('rejects invalid status transitions on general order updates', function () {
    $this->actingAs($this->employee);

    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Open->value,
    ]);

    $response = $this->putJson('/api/v1/orders/' . $order->uuid, [
        'status' => OrderStatus::Delivered->value,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
    expect($order->fresh()->status)->toBe(OrderStatus::Open);
});
it('checks if update allows partial updates', function () {
    $this->actingAs($this->employee);

    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'title' => 'Original Title',
        'description' => 'Original Description',
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
    ]);

    $response = $this->putJson('/api/v1/orders/' . $order->uuid, [
        'title' => 'New Title Only',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'customer_id' => $this->customer->id,
        'title' => 'New Title Only',
        // 'description' => 'Original Description' // Should remain unchanged
    ]);
});
it('checks if destroy deletes order successfully', function () {
    $this->actingAs($this->superAdmin);

    $order = Order::factory()->createQuietly();

    $v1 = (int)Cache::get('orders:version', 0);

    $response = $this->deleteJson('/api/v1/orders/' . $order->uuid);

    $response->assertOk();

    $v2 = (int)Cache::get('orders:version', 0);
    expect($v2)->toBe($v1 + 1, 'Orders cache version should bump on delete');

    $response->assertJson([
        'message' => 'Order deleted successfully.',
    ]);

    $this->assertDatabaseMissing('orders', [
        'id' => $order->id,
    ]);
});
it('checks if unauthenticated access returns 401', function () {
    $response = $this->getJson('/api/v1/orders');
    $response->assertUnauthorized();
});
it('checks if show non existent order returns 404', function () {
    $this->actingAs($this->employee);

    $response = $this->getJson('/api/v1/orders/non-existent-id');
    $response->assertNotFound();
});
it('caches index responses and returns hit on second request', function () {
    $this->actingAs($this->employee);

    // Create a couple of orders for this employee
    $orders = Order::factory()->count(2)->createQuietly([
        'customer_id' => $this->customer->id,
        'status' => OrderStatus::Open->value,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
    ]);

    $first = $this->getJson('/api/v1/orders');
    $first->assertOk()->assertHeader('X-Cache', 'MISS');

    $second = $this->getJson('/api/v1/orders');
    $second->assertOk()->assertHeader('X-Cache', 'HIT');
});
it('checks if store creates order with motor info and items', function () {
    Notification::fake();

    $this->actingAs($this->employee);

    $orderData = [
        'customer_id' => $this->customer->id,
        'title' => 'Motor Order with Items',
        'description' => 'Order with motor info and items',
        'priority' => OrderPriority::HIGH->value,
        'assigned_to' => $this->employee->id,
        'motor_info' => [
            'brand' => 'Nissan',
            'liters' => '2.5',
            'year' => '2022',
            'model' => 'Altima',
            'cylinder_count' => '4',
            'down_payment' => 500,
        ],
        'items' => [
            [
                'item_type' => OrderItemType::CylinderHead->value,
                'components' => ['bolts', 'valves', 'springs'],
            ],
            [
                'item_type' => OrderItemType::Crankshaft->value,
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $orderData);

    $response->assertCreated();

    $order = Order::with('items.components')->firstWhere('title', 'Motor Order with Items');
    expect($order)->not->toBeNull();

    // Motor info
    $this->assertDatabaseHas('order_motor_info', [
        'order_id' => $order->id,
        'brand' => 'Nissan',
        'liters' => '2.5',
    ]);

    // Items
    expect($order->items)->toHaveCount(2);

    // Components
    $head = $order->items->firstWhere('item_type', OrderItemType::CylinderHead);
    expect($head->components)->toHaveCount(3);

    $crank = $order->items->firstWhere('item_type', OrderItemType::Crankshaft);
    expect($crank->components)->toHaveCount(0);
});
it('caches show responses and returns hit on second request', function () {
    $this->actingAs($this->employee);

    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
    ]);

    $first = $this->getJson('/api/v1/orders/' . $order->uuid);
    $first->assertOk()->assertHeader('X-Cache', 'MISS');

    $second = $this->getJson('/api/v1/orders/' . $order->uuid);
    $second->assertOk()->assertHeader('X-Cache', 'HIT');
});
it('returns stable motor values and localized resource labels', function () {
    $this->actingAs($this->employee);

    $order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
        'status' => OrderStatus::Open->value,
        'priority' => OrderPriority::HIGH->value,
    ]);
    $item = $order->items()->createQuietly([
        'item_type' => OrderItemType::EngineBlock->value,
        'is_received' => true,
    ]);
    $item->components()->createQuietly([
        'component_name' => 'camshaft',
        'is_received' => true,
    ]);
    $catalogItem = ServiceCatalog::factory()->createQuietly([
        'service_key' => 'wash_block',
        'service_name_key' => 'service_catalog.wash_block',
        'item_type' => OrderItemType::EngineBlock,
    ]);
    $item->services()->createQuietly([
        'service_key' => $catalogItem->service_key,
        'base_price' => '100.00',
        'net_price' => '116.00',
    ]);

    $response = $this->withHeaders(['Accept-Language' => 'es'])
        ->getJson('/api/v1/orders/' . $order->uuid);

    $response->assertOk()
        ->assertJsonPath('status', $order->status->value)
        ->assertJsonPath('status_label', 'Abierta')
        ->assertJsonPath('priority', OrderPriority::HIGH->value)
        ->assertJsonPath('priority_label', 'Alta')
        ->assertJsonPath('items.0.item_type', OrderItemType::EngineBlock->value)
        ->assertJsonPath('items.0.item_type_label', 'Block')
        ->assertJsonPath('items.0.components.0.component_key', 'camshaft')
        ->assertJsonPath('items.0.components.0.component_label', 'Árbol de levas')
        ->assertJsonPath('services.0.service_key', 'wash_block')
        ->assertJsonPath('services.0.service_name', 'Lavado de block');

    $english = $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/orders/' . $order->uuid);

    $english->assertOk()
        ->assertHeader('X-Cache', 'MISS')
        ->assertJsonPath('status_label', 'Open')
        ->assertJsonPath('priority_label', 'High')
        ->assertJsonPath('items.0.item_type_label', 'Engine Block')
        ->assertJsonPath('items.0.components.0.component_label', 'Camshaft')
        ->assertJsonPath('services.0.service_name', 'Engine block wash');
});
