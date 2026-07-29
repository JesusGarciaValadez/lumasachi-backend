<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderLifecycleStatus as OrderStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderPayment;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderReviewedNotification;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $company = Company::factory()->create();

    $this->administrator = User::factory()->create([
        'role' => UserRole::ADMINISTRATOR->value,
        'company_id' => $company->id,
        'is_active' => true,
    ]);
    $this->superAdministrator = User::factory()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
        'company_id' => $company->id,
        'is_active' => true,
    ]);
    $this->inactiveAdministrator = User::factory()->create([
        'role' => UserRole::ADMINISTRATOR->value,
        'company_id' => $company->id,
        'is_active' => false,
    ]);
    $this->employee = User::factory()->create([
        'role' => UserRole::EMPLOYEE->value,
        'company_id' => $company->id,
        'is_active' => true,
    ]);
    $this->customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'is_active' => true,
    ]);

    Notification::fake();
    $this->actingAs($this->employee);
});
test('order creation notifies the customer and every active audit role', function () {
    $response = $this->postJson('/api/v1/orders', validOrderPayload());

    $response->assertCreated()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::AwaitingReview->value);

    Notification::assertSentTo($this->customer, OrderCreatedNotification::class);
    Notification::assertSentTo(
        $this->administrator,
        fn(OrderAuditNotification $notification): bool => $notification->event === 'created'
    );
    Notification::assertSentTo(
        $this->superAdministrator,
        fn(OrderAuditNotification $notification): bool => $notification->event === 'created'
    );
    Notification::assertNotSentTo($this->inactiveAdministrator, OrderAuditNotification::class);
});
test('order creation rejects a negative down payment', function () {
    $payload = validOrderPayload();
    $payload['motor_info']['down_payment'] = -1;

    $this->postJson('/api/v1/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['motor_info.down_payment']);

    $this->assertDatabaseEmpty('orders');
});
test('customer approval rejects a negative down payment', function () {
    $this->actingAs($this->customer);
    $order = createOrder(OrderStatus::AwaitingCustomerApproval);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);
    $catalog = createEdgeCaseCatalogService('wash_block', 600.00);
    $service = $item->services()->create([
        'service_key' => $catalog->service_key,
        'is_budgeted' => true,
        'base_price' => $catalog->base_price,
        'net_price' => $catalog->net_price,
    ]);

    $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => [$service->id],
        'down_payment' => -1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['down_payment']);

    expect($service->fresh()->is_authorized)->toBeFalse();
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingCustomerApproval);
});
test('budget rejects an item that belongs to another order', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $otherOrder = createOrder(OrderStatus::AwaitingReview);
    $otherItem = OrderItem::factory()->received()->create([
        'order_id' => $otherOrder->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);
    $catalog = createEdgeCaseCatalogService('wash_block', 600.00);

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [
            [
                'order_item_id' => $otherItem->id,
                'service_key' => $catalog->service_key,
                'measurement' => null,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0.order_item_id']);

    $this->assertDatabaseMissing('order_services', [
        'order_item_id' => $otherItem->id,
        'service_key' => $catalog->service_key,
    ]);
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingReview);
});
test('budget rejects an unreceived item', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
        'is_received' => false,
    ]);
    $catalog = createEdgeCaseCatalogService('wash_block', 600.00);

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [[
            'order_item_id' => $item->id,
            'service_key' => $catalog->service_key,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0.order_item_id']);
});
test('budget requires measurement for catalog services that need it', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);
    $catalog = createEdgeCaseCatalogService('deck_assembled_4cyl', 1600.00);

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [[
            'order_item_id' => $item->id,
            'service_key' => $catalog->service_key,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0.measurement']);

    $this->assertDatabaseMissing('order_services', ['order_item_id' => $item->id]);
});
test('budget rejects a service key that is not in the catalog', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [[
            'order_item_id' => $item->id,
            'service_key' => 'not_in_catalog',
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0.service_key']);

    $this->assertDatabaseMissing('order_services', ['order_item_id' => $item->id]);
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingReview);
});
test('budget rejects a service for a different item type', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);
    $catalog = ServiceCatalog::factory()->forItemType(OrderItemType::CylinderHead)->create();

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [[
            'order_item_id' => $item->id,
            'service_key' => $catalog->service_key,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0.service_key']);

    $this->assertDatabaseMissing('order_services', ['order_item_id' => $item->id]);
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingReview);
});
test('budget rejects an inactive catalog service', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);
    $catalog = ServiceCatalog::factory()->forItemType(OrderItemType::EngineBlock)->inactive()->create();

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => [[
            'order_item_id' => $item->id,
            'service_key' => $catalog->service_key,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0.service_key']);

    $this->assertDatabaseMissing('order_services', ['order_item_id' => $item->id]);
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingReview);
});
test('budget rejects a non array services payload', function () {
    $order = createOrder(OrderStatus::AwaitingReview);

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => 'invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['services']);

    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingReview);
});
test('customer approval rejects a service that belongs to another order', function () {
    $this->actingAs($this->customer);
    $order = createOrder(OrderStatus::AwaitingCustomerApproval);
    $otherOrder = createOrder(OrderStatus::AwaitingCustomerApproval);
    $otherService = createOrderService($otherOrder, 'wash_block', isAuthorized: false);

    $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => [$otherService->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['authorized_service_ids.0']);

    expect($otherService->fresh()->is_authorized)->toBeFalse();
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingCustomerApproval);
});
test('customer approval rejects a service that is not budgeted', function () {
    $this->actingAs($this->customer);
    $order = createOrder(OrderStatus::AwaitingCustomerApproval);
    $service = createOrderService($order, 'wash_block', isAuthorized: false);
    $service->update(['is_budgeted' => false]);

    $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => [$service->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['authorized_service_ids.0']);

    expect($service->fresh()->is_authorized)->toBeFalse();
});
test('customer approval rejects a non integer service id', function () {
    $this->actingAs($this->customer);
    $order = createOrder(OrderStatus::AwaitingCustomerApproval);

    $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => ['invalid'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['authorized_service_ids.0']);

    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingCustomerApproval);
});
test('a different customer cannot submit approval', function () {
    $otherCustomer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'is_active' => true,
    ]);
    $order = createOrder(OrderStatus::AwaitingCustomerApproval);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $service = $item->services()->create([
        'service_key' => 'wash_block',
        'is_budgeted' => true,
        'base_price' => 600.00,
        'net_price' => 696.00,
    ]);

    $this->actingAs($otherCustomer)
        ->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => [$service->id],
        ])->assertForbidden();

    expect($service->fresh()->is_authorized)->toBeFalse();
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingCustomerApproval);
});
test('work completion rejects a service that belongs to another order', function () {
    $order = createOrder(OrderStatus::ReadyForWork);
    $otherOrder = createOrder(OrderStatus::ReadyForWork);
    $otherService = createOrderService($otherOrder, 'wash_block', isAuthorized: true);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$otherService->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['completed_service_ids.0']);

    expect($otherService->fresh()->is_completed)->toBeFalse();
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::ReadyForWork);
});
test('work completion rejects a service that is not authorized', function () {
    $order = createOrder(OrderStatus::ReadyForWork);
    $unauthorizedService = createOrderService($order, 'wash_block', isAuthorized: false);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$unauthorizedService->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['completed_service_ids.0']);

    expect($unauthorizedService->fresh()->is_completed)->toBeFalse();
});
test('work completion rejects a service that is already completed', function () {
    $order = createOrder(OrderStatus::ReadyForWork);
    $completedService = createOrderService($order, 'wash_block', isAuthorized: true);
    $completedService->update(['is_completed' => true]);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$completedService->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['completed_service_ids.0']);

    expect($completedService->fresh()->is_completed)->toBeTrue();
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::ReadyForWork);
});
test('work completion rejects a mixed authorized and unauthorized selection atomically', function () {
    $order = createOrder(OrderStatus::ReadyForWork);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);
    $authorizedService = $item->services()->create([
        'service_key' => 'wash_block',
        'is_budgeted' => true,
        'is_authorized' => true,
        'base_price' => 600.00,
        'net_price' => 696.00,
    ]);
    $unauthorizedService = $item->services()->create([
        'service_key' => 'inspect_block',
        'is_budgeted' => true,
        'is_authorized' => false,
        'base_price' => 600.00,
        'net_price' => 696.00,
    ]);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$authorizedService->id, $unauthorizedService->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['completed_service_ids.1']);

    expect($authorizedService->fresh()->is_completed)->toBeFalse();
    expect($unauthorizedService->fresh()->is_completed)->toBeFalse();
});
test('work completion rejects duplicate service ids', function () {
    $order = createOrder(OrderStatus::ReadyForWork);
    $service = createOrderService($order, 'wash_block', isAuthorized: true);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => [$service->id, $service->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['completed_service_ids.1']);

    expect($service->fresh()->is_completed)->toBeFalse();
});
test('work completion rejects a non integer service id', function () {
    $order = createOrder(OrderStatus::ReadyForWork);

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => ['invalid'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['completed_service_ids.0']);

    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::ReadyForWork);
});
test('order creation rejects a component from a different item type', function () {
    $payload = validOrderPayload();
    $payload['items'][0]['components'] = ['valves'];

    $this->postJson('/api/v1/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.components.0']);

    $this->assertDatabaseEmpty('orders');
});
test('quotation totals follow budgeted authorized and completed services', function () {
    $order = createOrder(OrderStatus::AwaitingReview);
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);

    $catalog = collect([
        'wash_block' => 600.00,
        'weld_between_cylinders_qr25' => 800.00,
        'deck_assembled_4cyl' => 1600.00,
        'replace_cam_bearings' => 480.00,
        'polish_camshaft_bars' => 280.00,
    ])->mapWithKeys(fn(float $price, string $serviceKey): array => [
        $serviceKey => createEdgeCaseCatalogService($serviceKey, $price),
    ]);

    $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
        'services' => $catalog->keys()
            ->map(fn(string $serviceKey): array => [
                'order_item_id' => $item->id,
                'service_key' => $serviceKey,
                'measurement' => $serviceKey === 'deck_assembled_4cyl' ? '20' : null,
            ])
            ->values()
            ->all(),
    ])->assertOk();

    $order->refresh();
    expect($order->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingCustomerApproval);
    expect((float)$order->services()->sum('base_price'))->toBe(3760.00);
    expect((float)$order->services()->sum('net_price'))->toBe(4361.60);

    Notification::assertSentTo($this->customer, OrderReviewedNotification::class);
    Notification::assertSentTo(
        $this->administrator,
        fn(OrderAuditNotification $notification): bool => $notification->event === 'reviewed'
    );
    Notification::assertSentTo(
        $this->superAdministrator,
        fn(OrderAuditNotification $notification): bool => $notification->event === 'reviewed'
    );

    $services = $order->services()->get()->keyBy('service_key');
    $authorizedServiceIds = collect([
        'wash_block',
        'weld_between_cylinders_qr25',
        'replace_cam_bearings',
    ])->map(fn(string $serviceKey): int => $services->get($serviceKey)->id)->all();

    $this->actingAs($this->customer);
    $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
        'authorized_service_ids' => $authorizedServiceIds,
    ])->assertOk();

    $authorizedServices = $order->services()->where('is_authorized', true);
    expect((float)$authorizedServices->sum('base_price'))->toBe(1880.00);
    expect((float)$authorizedServices->sum('net_price'))->toBe(2180.80);
    expect($services->get('deck_assembled_4cyl')->fresh()->is_authorized)->toBeFalse();
    expect($services->get('polish_camshaft_bars')->fresh()->is_authorized)->toBeFalse();

    $this->actingAs($this->employee);
    $completedServiceIds = collect([
        'wash_block',
        'replace_cam_bearings',
    ])->map(fn(string $serviceKey): int => $services->get($serviceKey)->id)->all();

    $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
        'completed_service_ids' => $completedServiceIds,
    ])->assertOk();

    expect($order->fresh()->completedTotal())->toBe('1252.80');
    expect($services->get('weld_between_cylinders_qr25')->fresh()->is_completed)->toBeFalse();
});
test('delivery requires the remaining balance to be paid', function () {
    $order = createOrder(OrderStatus::ReadyForDelivery);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);
    OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => true,
        'net_price' => 1252.80,
    ]);
    OrderPayment::factory()->create(['order_id' => $order->id, 'amount' => 1000.00, 'created_by' => $this->employee->id]);

    $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::ReadyForDelivery);
    Notification::assertNotSentTo($this->customer, OrderDeliveredNotification::class);
    Notification::assertNotSentTo($this->administrator, OrderAuditNotification::class);

    OrderPayment::factory()->create(['order_id' => $order->id, 'amount' => 252.80, 'created_by' => $this->employee->id]);

    $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
        ->assertOk()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::Delivered->value);
});
test('delivery accepts exact payment overpayment and zero total orders', function () {
    foreach ([
                 ['total' => 100.00, 'paid' => 100.00],
                 ['total' => 100.00, 'paid' => 125.00],
                 ['total' => 0.00, 'paid' => 0.00],
             ] as $payment) {
        $order = createOrder(OrderStatus::ReadyForDelivery);
        if ($payment['total'] > 0) {
            $item = OrderItem::factory()->create(['order_id' => $order->id]);
            OrderService::factory()->create([
                'order_item_id' => $item->id,
                'is_completed' => true,
                'net_price' => $payment['total'],
            ]);
        }
        if ($payment['paid'] > 0) {
            OrderPayment::factory()->create([
                'order_id' => $order->id,
                'amount' => $payment['paid'],
                'created_by' => $this->employee->id,
            ]);
        }

        $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
            ->assertOk()
            ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::Delivered->value);
    }
});
test('reviewed to awaiting customer approval records both status changes', function () {
    $order = createOrder(OrderStatus::AwaitingReview);

    $order->update([
        'lifecycle_status' => OrderLifecycleStatus::Reviewed->value,
        'updated_by' => $this->employee->id,
    ]);

    $statusChanges = $order->orderHistories()
        ->where('field_changed', 'lifecycle_status')
        ->oldest('id')
        ->get();

    expect($statusChanges)->toHaveCount(2);
    expect($statusChanges[0]->old_value)->toBe(OrderStatus::AwaitingReview);
    expect($statusChanges[0]->new_value)->toBe(OrderStatus::Reviewed);
    expect($statusChanges[1]->old_value)->toBe(OrderStatus::Reviewed);
    expect($statusChanges[1]->new_value)->toBe(OrderStatus::AwaitingCustomerApproval);
    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::AwaitingCustomerApproval);
});
test('delivery notifies the customer and every active audit role', function () {
    $order = createOrder(OrderStatus::ReadyForDelivery);

    $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
        ->assertOk()
        ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::Delivered->value);

    Notification::assertSentTo($this->customer, OrderDeliveredNotification::class);
    Notification::assertSentTo(
        $this->administrator,
        fn(OrderAuditNotification $notification): bool => $notification->event === 'delivered'
    );
    Notification::assertSentTo(
        $this->superAdministrator,
        fn(OrderAuditNotification $notification): bool => $notification->event === 'delivered'
    );
    Notification::assertNotSentTo($this->inactiveAdministrator, OrderAuditNotification::class);
});
/**
 * @return array<string, mixed>
 */
function validOrderPayload(): array
{
    return [
        'customer_id' => test()->customer->id,
        'title' => 'Block review',
        'description' => 'Block received for review',
        'priority' => OrderPriority::NORMAL->value,
        'assigned_to' => test()->employee->id,
        'motor_info' => [
            'brand' => null,
            'liters' => null,
            'year' => null,
            'model' => null,
            'cylinder_count' => null,
            'down_payment' => 0,
        ],
        'items' => [
            [
                'item_type' => OrderItemType::EngineBlock->value,
                'components' => ['camshaft', 'bearing_caps', 'cap_bolts'],
            ],
        ],
    ];
}

function createOrder(OrderStatus $status): Order
{
    $order = Order::factory()->createQuietly([
        'customer_id' => test()->customer->id,
        'assigned_to' => test()->employee->id,
        'created_by' => test()->employee->id,
        'updated_by' => test()->employee->id,
        'status' => $status->value,
    ]);

    OrderMotorInfo::create([
        'order_id' => $order->id,
    ]);

    return $order;
}

function createEdgeCaseCatalogService(string $serviceKey, float $basePrice): ServiceCatalog
{
    return ServiceCatalog::create([
        'service_key' => $serviceKey,
        'service_name_key' => "service_catalog.{$serviceKey}",
        'item_type' => OrderItemType::EngineBlock->value,
        'base_price' => $basePrice,
        'tax_percentage' => 16.00,
        'requires_measurement' => $serviceKey === 'deck_assembled_4cyl',
        'is_active' => true,
        'display_order' => 1,
    ]);
}

function createOrderService(Order $order, string $serviceKey, bool $isAuthorized): OrderService
{
    $item = OrderItem::factory()->received()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);

    return $item->services()->create([
        'service_key' => $serviceKey,
        'is_budgeted' => true,
        'is_authorized' => $isAuthorized,
        'is_completed' => false,
        'base_price' => 600.00,
        'net_price' => 696.00,
    ]);
}
