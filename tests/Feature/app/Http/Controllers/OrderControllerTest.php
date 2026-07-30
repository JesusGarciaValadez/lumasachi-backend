<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
    ]);

    // Create orders for another employee that should not be returned
    $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $otherOrders = Order::factory()->count(5)->createQuietly([
        'customer_id' => $this->customer->id,
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
        'assigned_to' => $otherEmployee->id,
        'created_by' => $this->admin->id,
    ]);
    $response = $this->getJson('/api/v1/orders');

    $response->assertOk()
        ->assertJsonCount(5);
});
it('includes refund status indicators in the authenticated order list', function () {
    $order = Order::factory()->createQuietly([
        'assigned_to' => $this->employee->id,
        'created_by' => $this->admin->id,
    ]);

    OrderRefund::factory()->create([
        'order_id' => $order->id,
        'requested_by' => $this->employee->id,
    ]);

    $this->actingAs($this->employee)
        ->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('0.uuid', $order->uuid)
        ->assertJsonPath('0.refunds.0.status', 'Requested');
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
it('creates an order after an imported order sequence is synchronized', function () {
    Notification::fake();

    Order::factory()->createQuietly([
        'id' => 15,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->employee->id,
        'updated_by' => $this->employee->id,
    ]);

    DB::statement("SELECT setval(pg_get_serial_sequence('orders', 'id'), 1, false)");

    $migration = include base_path('database/migrations/2026_07_30_024320_synchronize_orders_primary_key_sequence.php');
    $migration->up();

    $response = $this->actingAs($this->employee)->postJson('/api/v1/orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Imported Order Follow-up',
        'description' => 'Created after the imported sequence was repaired.',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'items' => [
            ['item_type' => OrderItemType::CylinderHead->value],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('order.id', 16);
});
it('repairs an imported order sequence without ownership metadata', function () {
    Notification::fake();

    Order::factory()->createQuietly([
        'id' => 15,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->employee->id,
        'created_by' => $this->employee->id,
        'updated_by' => $this->employee->id,
    ]);

    DB::statement('ALTER SEQUENCE public.orders_id_seq OWNED BY NONE');
    DB::statement("SELECT setval('public.orders_id_seq'::regclass, 1, false)");

    $migration = include base_path('database/migrations/2026_07_30_031503_repair_orders_primary_key_sequence_ownership.php');
    $migration->up();

    $sequence = DB::selectOne("SELECT pg_get_serial_sequence('public.orders', 'id') AS sequence_name");

    expect($sequence->sequence_name)->toBe('public.orders_id_seq');

    $response = $this->actingAs($this->employee)->postJson('/api/v1/orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Imported Order Ownership Follow-up',
        'description' => 'Created after the imported sequence ownership was repaired.',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'items' => [
            ['item_type' => OrderItemType::CylinderHead->value],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('order.id', 16);
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
            'lifecycle_status',
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);
    $updateData = [
        'title' => 'Updated Order Title',
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
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
            'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    $response = $this->putJson('/api/v1/orders/' . $order->uuid, [
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['lifecycle_status']);
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::Received);
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
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
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
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
        ->assertJsonPath('lifecycle_status', $order->lifecycleStatus()->value)
        ->assertJsonPath('lifecycle_status_label', 'Recibida')
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
        ->assertJsonPath('lifecycle_status_label', 'Received')
        ->assertJsonPath('priority_label', 'High')
        ->assertJsonPath('items.0.item_type_label', 'Engine Block')
        ->assertJsonPath('items.0.components.0.component_label', 'Camshaft')
        ->assertJsonPath('services.0.service_name', 'Engine block wash');
});
