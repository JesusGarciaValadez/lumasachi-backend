<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderPayment;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    config(['cache.default' => 'array']);
    Cache::flush();

    $this->company = Company::factory()->create();
    $this->admin = User::factory()->create([
        'role' => UserRole::ADMINISTRATOR->value,
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->employee = User::factory()->create([
        'role' => UserRole::EMPLOYEE->value,
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'is_active' => true,
    ]);
});
it('creates order with motor info and items via api', function () {
    $this->actingAs($this->employee);

    $payload = [
        'customer_id' => $this->customer->id,
        'title' => 'Motor Rebuild #1',
        'description' => 'Full engine rebuild',
        'priority' => OrderPriority::HIGH->value,
        'assigned_to' => $this->employee->id,
        'motor_info' => [
            'brand' => 'Toyota',
            'liters' => '3.5',
            'year' => '2019',
            'model' => 'Camry',
            'cylinder_count' => '6',
            'down_payment' => 1500,
        ],
        'items' => [
            [
                'item_type' => OrderItemType::CylinderHead->value,
                'components' => ['bolts', 'valves'],
            ],
            [
                'item_type' => OrderItemType::EngineBlock->value,
                'components' => ['bearing_caps', 'camshaft'],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $payload);

    $response->assertCreated()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::AwaitingReview->value);

    // Verify DB state
    $this->assertDatabaseHas('orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Motor Rebuild #1',
    ]);

    $order = Order::with('items.components')->firstWhere('title', 'Motor Rebuild #1');
    expect($order)->not->toBeNull();
    expect($order->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingReview);

    // Motor info
    $motorInfo = OrderMotorInfo::query()->where('order_id', $order->id)->first();
    expect($motorInfo)->not->toBeNull();
    expect($motorInfo->only(['brand', 'liters', 'year', 'model', 'cylinder_count']))->toBe([
        'brand' => 'Toyota',
        'liters' => '3.5',
        'year' => '2019',
        'model' => 'Camry',
        'cylinder_count' => '6',
    ]);

    // The advance is an append-only ledger entry attributable to the creator.
    expect($order->payments()->count())->toBe(1);
    $this->assertDatabaseHas('order_payments', [
        'order_id' => $order->id,
        'amount' => '1500.00',
        'created_by' => $this->employee->id,
    ]);

    $statusHistory = OrderHistory::query()
        ->where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_LIFECYCLE_STATUS)
        ->first();
    expect($statusHistory)->not->toBeNull();
    expect($statusHistory->old_value)->toBe(OrderLifecycleStatus::Received);
    expect($statusHistory->new_value)->toBe(OrderLifecycleStatus::AwaitingReview);
    expect($statusHistory->created_by)->toBe($this->employee->id);

    // Items
    expect($order->items)->toHaveCount(2);

    // Components
    $cylinderHead = $order->items->firstWhere('item_type', OrderItemType::CylinderHead);
    expect($cylinderHead->components)->toHaveCount(2);
});
it('creates a received piece without optional components', function () {
    $this->actingAs($this->employee);

    $payload = [
        'customer_id' => $this->customer->id,
        'title' => 'Head without components',
        'description' => 'Only the top-level piece was received',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'items' => [
            ['item_type' => OrderItemType::CylinderHead->value],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $payload);

    $response->assertCreated()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::AwaitingReview->value);

    $order = Order::query()->where('title', 'Head without components')->firstOrFail();
    $item = $order->items()->with('components')->firstOrFail();

    expect($item->item_type)->toBe(OrderItemType::CylinderHead);
    expect($item->is_received)->toBeTrue();
    expect($item->components)->toBeEmpty();
});
it('validates required fields for order creation', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson('/api/v1/orders', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'title', 'description', 'priority', 'assigned_to', 'items']);
});
it('rejects order creation without received items', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson('/api/v1/orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Test',
        'description' => 'Test',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'items' => [],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['items']);
    $this->assertDatabaseEmpty('orders');
});
it('rejects an invalid advance payment', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson('/api/v1/orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Test',
        'description' => 'Test',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'motor_info' => ['down_payment' => 'not-money'],
        'items' => [
            ['item_type' => OrderItemType::EngineBlock->value],
        ],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['motor_info.down_payment']);
    $this->assertDatabaseEmpty('orders');
});
it('validates item types', function () {
    $this->actingAs($this->employee);

    $response = $this->postJson('/api/v1/orders', [
        'customer_id' => $this->customer->id,
        'title' => 'Test',
        'description' => 'Test',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'items' => [
            ['item_type' => 'invalid_type'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.item_type']);
});
it('requires authentication to create order', function () {
    $response = $this->postJson('/api/v1/orders', []);
    $response->assertUnauthorized();
});
it('rejects an unknown customer without creating partial order data', function () {
    $this->actingAs($this->employee);

    $payload = [
        'customer_id' => 999999,
        'title' => 'Invalid customer',
        'description' => 'The order must not be created',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'motor_info' => ['down_payment' => 100.00],
        'items' => [['item_type' => OrderItemType::EngineBlock->value, 'components' => ['camshaft']]],
    ];

    $this->postJson('/api/v1/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);

    $this->assertDatabaseEmpty('orders');
    $this->assertDatabaseEmpty('order_motor_info');
    $this->assertDatabaseEmpty('order_items');
    $this->assertDatabaseEmpty('order_payments');
    $this->assertDatabaseEmpty('order_histories');
});
it('rejects an unknown assignee without creating partial order data', function () {
    $this->actingAs($this->employee);

    $payload = [
        'customer_id' => $this->customer->id,
        'title' => 'Invalid assignee',
        'description' => 'The order must not be created',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => 999999,
        'motor_info' => ['down_payment' => 100.00],
        'items' => [['item_type' => OrderItemType::EngineBlock->value, 'components' => ['camshaft']]],
    ];

    $this->postJson('/api/v1/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to']);

    $this->assertDatabaseEmpty('orders');
    $this->assertDatabaseEmpty('order_motor_info');
    $this->assertDatabaseEmpty('order_items');
    $this->assertDatabaseEmpty('order_payments');
    $this->assertDatabaseEmpty('order_histories');
});
it('submits budget for order', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::CylinderHead->value,
    ]);
    $catalog = createLifecycleCatalogService('wash_block', 600.00);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [
            [
                'order_item_id' => $item->id,
                'service_key' => $catalog->service_key,
                'measurement' => null,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('order.financials.budgeted_base', '600.00')
        ->assertJsonPath('order.financials.budgeted_net', '696.00');

    // Order should transition through REVIEWED to AWAITING_CUSTOMER_APPROVAL
    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::AwaitingCustomerApproval);
});
it('rejects budget for wrong status', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::Received);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [],
    ]);

    $response->assertUnprocessable();
});
it('approves services via api', function () {
    $this->actingAs($this->customer);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::AwaitingCustomerApproval);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $svc = OrderService::factory()->budgeted()->create([
        'order_item_id' => $item->id,
        'base_price' => 500.00,
        'net_price' => 580.00,
    ]);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => [$svc->id],
        'down_payment' => '300.00',
    ]);

    $response->assertOk()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::ReadyForWork->value)
        ->assertJsonPath('order.financials.authorized', '580.00')
        ->assertJsonPath('order.financials.advance_payment', '300.00');

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::ReadyForWork);
    expect($order->totalPaid())->toBe('300.00');
    expect(OrderPayment::where('order_id', $order->id)->count())->toBe(1);
});
it('rejects approval for wrong status', function () {
    $this->actingAs($this->customer);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::Received);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => [1],
    ]);

    $response->assertUnprocessable();
});
it('marks services completed via api', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::ReadyForWork);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $svc = OrderService::factory()->budgeted()->authorized()->create([
        'order_item_id' => $item->id,
        'base_price' => 500.00,
        'net_price' => 580.00,
    ]);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$svc->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('order.financials.completed', '580.00')
        ->assertJsonPath('order.financials.remaining_balance', '580.00');

    $svc->refresh();
    expect($svc->is_completed)->toBeTrue();
});
it('rejects work completion for wrong status', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::Received);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $svc = OrderService::factory()->budgeted()->authorized()->create(['order_item_id' => $item->id]);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$svc->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status']);

    expect($svc->fresh()->is_completed)->toBeFalse();
    expect($order->fresh()->lifecycle_status)->toBe(OrderLifecycleStatus::Received);
});
it('marks order ready for delivery via api', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::ReadyForWork);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/ready-for-delivery");

    $response->assertOk();

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::ReadyForDelivery);
});
it('rejects ready for delivery for wrong status', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::Received);

    $this->postJson("/api/v1/orders/{$order->uuid}/ready-for-delivery")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lifecycle_status']);

    expect($order->fresh()->lifecycle_status)->toBe(OrderLifecycleStatus::Received);
});
it('delivers order via api', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::ReadyForDelivery);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/deliver");

    $response->assertOk();

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::Delivered);
});
it('rejects deliver for wrong status', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::Received);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/deliver");

    $response->assertUnprocessable();
});
it('completes full motor order lifecycle', function () {
    $this->actingAs($this->employee);

    $catalog = createLifecycleCatalogService('pressure_test_head', 450.00);

    // Step 1: Create order with items
    $payload = [
        'customer_id' => $this->customer->id,
        'title' => 'Full Lifecycle Test',
        'description' => 'Complete e2e test',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => $this->employee->id,
        'motor_info' => [
            'brand' => 'Ford',
            'liters' => '5.0',
            'year' => '2021',
            'model' => 'Mustang',
            'cylinder_count' => '8',
        ],
        'items' => [
            [
                'item_type' => OrderItemType::CylinderHead->value,
                'components' => ['bolts', 'valves'],
            ],
        ],
    ];

    $createResponse = $this->postJson('/api/v1/orders', $payload);
    $createResponse->assertCreated();

    $order = Order::firstWhere('title', 'Full Lifecycle Test');
    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::AwaitingReview);

    $item = $order->items->first();

    // Step 2: Submit budget
    $budgetResponse = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [
            [
                'order_item_id' => $item->id,
                'service_key' => $catalog->service_key,
                'measurement' => null,
            ],
        ],
    ]);
    $budgetResponse->assertOk();

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::AwaitingCustomerApproval);

    // Step 3: Customer approval
    $this->actingAs($this->customer);
    $serviceId = $order->services->first()->id;
    $approvalResponse = $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => [$serviceId],
        'down_payment' => $catalog->net_price,
    ]);
    $approvalResponse->assertOk();

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::ReadyForWork);

    // Step 4: Mark work completed
    $this->actingAs($this->employee);
    $workResponse = $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$serviceId],
    ]);
    $workResponse->assertOk();

    // Step 5: Ready for delivery
    $readyResponse = $this->postJson("/api/v1/orders/{$order->uuid}/ready-for-delivery");
    $readyResponse->assertOk();

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::ReadyForDelivery);

    // Step 6: Deliver
    $deliverResponse = $this->postJson("/api/v1/orders/{$order->uuid}/deliver");
    $deliverResponse->assertOk();

    $order->refresh();
    expect($order->lifecycle_status)->toEqual(OrderLifecycleStatus::Delivered);
});
it('forbids customer from submitting budget', function () {
    $this->actingAs($this->customer);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::AwaitingReview);

    $response = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [],
    ]);

    $response->assertForbidden();
});
it('forbids customer from delivering an order', function () {
    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::ReadyForDelivery);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/orders/{$order->uuid}/deliver")
        ->assertForbidden();

    expect($order->fresh()->lifecycle_status)->toBe(OrderLifecycleStatus::ReadyForDelivery);
});
it('returns order with motor info items and services', function () {
    $this->actingAs($this->employee);

    $order = createLifecycleOrderInStatus(OrderLifecycleStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    OrderService::factory()->budgeted()->create([
        'order_item_id' => $item->id,
        'base_price' => 500.00,
        'net_price' => 580.00,
    ]);

    $response = $this->getJson("/api/v1/orders/{$order->uuid}");

    $response->assertOk()
        ->assertJsonStructure([
            'motor_info',
            'items',
            'services',
        ]);
});
// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function createLifecycleOrderInStatus(OrderLifecycleStatus $status): Order
{
    $order = Order::factory()->createQuietly([
        'customer_id' => test()->customer->id,
        'assigned_to' => test()->employee->id,
        'created_by' => test()->employee->id,
        'updated_by' => test()->employee->id,
        'lifecycle_status' => $status->value,
    ]);

    OrderMotorInfo::create([
        'order_id' => $order->id,
    ]);

    return $order;
}

function createLifecycleCatalogService(string $key, float $price): ServiceCatalog
{
    return ServiceCatalog::create([
        'service_key' => $key,
        'service_name_key' => "service_catalog.{$key}",
        'item_type' => OrderItemType::CylinderHead->value,
        'base_price' => $price,
        'tax_percentage' => 16.00,
        'requires_measurement' => false,
        'is_active' => true,
        'display_order' => 1,
    ]);
}
