<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderPayment;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderLifecycleService;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

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

    $this->service = app(OrderLifecycleService::class);
});
it('creates order with motor info and items', function () {
    $data = validOrderData($this->customer, $this->employee);

    $order = $this->service->createOrderWithMotorItems($data, $this->employee);

    // Order created with correct status
    expect($order)->toBeInstanceOf(Order::class);
    expect($order->customer_id)->toBe($this->customer->id);
    expect($order->title)->toBe('Test Motor Order');

    // Motor info created
    expect($order->motorInfo)->not->toBeNull();
    expect($order->motorInfo->brand)->toBe('Honda');
    expect($order->motorInfo->liters)->toBe('2.0');
    expect($order->motorInfo->year)->toBe('2020');

    // Items created
    expect($order->items)->toHaveCount(2);
    $cylinderHead = $order->items->firstWhere('item_type', OrderItemType::CylinderHead);
    expect($cylinderHead)->not->toBeNull();
    expect($cylinderHead->is_received)->toBeTrue();

    // Components created for cylinder head
    expect($cylinderHead->components->count())->toBeGreaterThan(0);
});
it('creates order and transitions to awaiting review', function () {
    $data = validOrderData($this->customer, $this->employee);

    $order = $this->service->createOrderWithMotorItems($data, $this->employee);
    $order->refresh();

    expect($order->status)->toBe(OrderStatus::AwaitingReview);
});
it('creates order with empty motor info', function () {
    $data = validOrderData($this->customer, $this->employee);
    $data['motor_info'] = [];

    $order = $this->service->createOrderWithMotorItems($data, $this->employee);

    expect($order)->toBeInstanceOf(Order::class);
    expect($order->motorInfo)->not->toBeNull();
});
it('creates items without components', function () {
    $data = validOrderData($this->customer, $this->employee);
    $data['items'] = [
        ['item_type' => OrderItemType::Crankshaft->value],
    ];

    $order = $this->service->createOrderWithMotorItems($data, $this->employee);

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->components)->toHaveCount(0);
});
it('submits budget for order in awaiting review', function () {
    $order = createOrderInStatus(OrderStatus::AwaitingReview, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $catalog = createCatalogService('wash_block', 600.00);

    $servicesData = [
        [
            'order_item_id' => $item->id,
            'service_key' => $catalog->service_key,
            'measurement' => null,
        ],
    ];

    $result = $this->service->submitBudget($order, $servicesData, $this->employee);

    // Service created as budgeted
    expect($result->services)->toHaveCount(1);
    $svc = $result->services->first();
    expect($svc->is_budgeted)->toBeTrue();
    expect((float)$svc->base_price)->toBe(600.00);
    expect((float)$svc->net_price)->toBe($catalog->net_price);
});
it('transitions to reviewed after budget', function () {
    $order = createOrderInStatus(OrderStatus::AwaitingReview, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $catalog = createCatalogService('wash_block', 600.00);

    $this->service->submitBudget($order, [
        ['order_item_id' => $item->id, 'service_key' => $catalog->service_key, 'measurement' => null],
    ], $this->employee);

    $order->refresh();

    // Observer auto-transitions REVIEWED → AWAITING_CUSTOMER_APPROVAL
    expect($order->status)->toBe(OrderStatus::AwaitingCustomerApproval);
});
it('rejects budget for order not in awaiting review', function () {
    $order = createOrderInStatus(OrderStatus::Open, $this->customer, $this->employee);

    $this->expectException(InvalidArgumentException::class);

    $this->service->submitBudget($order, [], $this->employee);
});
it('rejects budget for an item from another order', function () {
    $order = createOrderInStatus(OrderStatus::AwaitingReview, $this->customer, $this->employee);
    $otherOrder = createOrderInStatus(OrderStatus::AwaitingReview, $this->customer, $this->employee);
    $otherItem = OrderItem::factory()->received()->create(['order_id' => $otherOrder->id]);
    $catalog = createCatalogService('wash_block', 600.00);

    try {
        $this->service->submitBudget($order, [
            [
                'order_item_id' => $otherItem->id,
                'service_key' => $catalog->service_key,
                'measurement' => null,
            ],
        ], $this->employee);
        $this->fail('A budget item from another order must be rejected.');
    } catch (InvalidArgumentException) {
    }

    $this->assertDatabaseMissing('order_services', [
        'order_item_id' => $otherItem->id,
        'service_key' => $catalog->service_key,
    ]);
});
it('approves services and sets down payment', function () {
    $order = createOrderInStatus(OrderStatus::AwaitingCustomerApproval, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $svc = OrderService::factory()->budgeted()->create([
        'order_item_id' => $item->id,
        'base_price' => 500.00,
        'net_price' => 580.00,
    ]);

    $result = $this->service->customerApproval($order, [$svc->id], 200.00, $this->customer);

    $svc->refresh();
    expect($svc->is_authorized)->toBeTrue();

    $result->refresh();
    expect($result->totalPaid())->toBe('200.00');
    expect(OrderPayment::where('order_id', $order->id)->count())->toBe(1);
    expect($result->status)->toBe(OrderStatus::ReadyForWork);
});
it('rejects approval for wrong status', function () {
    $order = createOrderInStatus(OrderStatus::Open, $this->customer, $this->employee);

    $this->expectException(InvalidArgumentException::class);

    $this->service->customerApproval($order, [], null, $this->customer);
});
it('rejects approval for a service from another order', function () {
    $order = createOrderInStatus(OrderStatus::AwaitingCustomerApproval, $this->customer, $this->employee);
    $otherOrder = createOrderInStatus(OrderStatus::AwaitingCustomerApproval, $this->customer, $this->employee);
    $otherItem = OrderItem::factory()->received()->create(['order_id' => $otherOrder->id]);
    $otherService = OrderService::factory()->budgeted()->create([
        'order_item_id' => $otherItem->id,
        'is_authorized' => false,
    ]);

    try {
        $this->service->customerApproval($order, [$otherService->id], null, $this->customer);
        $this->fail('A service from another order must be rejected during approval.');
    } catch (InvalidArgumentException) {
    }

    $otherService->refresh();
    expect($otherService->is_authorized)->toBeFalse();
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingCustomerApproval);
});
it('rejects approval for a non budgeted service', function () {
    $order = createOrderInStatus(OrderStatus::AwaitingCustomerApproval, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $service = OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_budgeted' => false,
        'is_authorized' => false,
    ]);

    $this->expectException(InvalidArgumentException::class);

    $this->service->customerApproval($order, [$service->id], null, $this->customer);
});
it('marks services as completed', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForWork, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $svc = OrderService::factory()->budgeted()->authorized()->create([
        'order_item_id' => $item->id,
        'base_price' => 500.00,
        'net_price' => 580.00,
    ]);

    $this->service->markWorkCompleted($order, [$svc->id], $this->employee);

    $svc->refresh();
    expect($svc->is_completed)->toBeTrue();
});
it('marks work completed from in progress', function () {
    $order = createOrderInStatus(OrderStatus::InProgress, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $svc = OrderService::factory()->budgeted()->authorized()->create([
        'order_item_id' => $item->id,
        'base_price' => 500.00,
        'net_price' => 580.00,
    ]);

    $this->service->markWorkCompleted($order, [$svc->id], $this->employee);

    $svc->refresh();
    expect($svc->is_completed)->toBeTrue();
});
it('rejects work completed for wrong status', function () {
    $order = createOrderInStatus(OrderStatus::Open, $this->customer, $this->employee);

    $this->expectException(InvalidArgumentException::class);

    $this->service->markWorkCompleted($order, [], $this->employee);
});
it('rejects work completed for a service from another order', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForWork, $this->customer, $this->employee);
    $otherOrder = createOrderInStatus(OrderStatus::ReadyForWork, $this->customer, $this->employee);
    $otherItem = OrderItem::factory()->received()->create(['order_id' => $otherOrder->id]);
    $otherService = OrderService::factory()->budgeted()->authorized()->create([
        'order_item_id' => $otherItem->id,
        'is_completed' => false,
    ]);

    try {
        $this->service->markWorkCompleted($order, [$otherService->id], $this->employee);
        $this->fail('A service from another order must be rejected during completion.');
    } catch (InvalidArgumentException) {
    }

    $otherService->refresh();
    expect($otherService->is_completed)->toBeFalse();
    expect($order->fresh()->status)->toBe(OrderStatus::ReadyForWork);
});
it('rejects work completed when any service is unauthorized', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForWork, $this->customer, $this->employee);
    $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
    $authorizedService = OrderService::factory()->budgeted()->authorized()->create([
        'order_item_id' => $item->id,
        'is_completed' => false,
    ]);
    $unauthorizedService = OrderService::factory()->budgeted()->create([
        'order_item_id' => $item->id,
        'is_authorized' => false,
        'is_completed' => false,
    ]);

    try {
        $this->service->markWorkCompleted(
            $order,
            [$authorizedService->id, $unauthorizedService->id],
            $this->employee
        );
        $this->fail('Unauthorized services must not be marked as completed.');
    } catch (InvalidArgumentException) {
    }

    expect($authorizedService->fresh()->is_completed)->toBeFalse();
    expect($unauthorizedService->fresh()->is_completed)->toBeFalse();
});
it('marks order ready for delivery', function () {
    $order = createOrderInStatus(OrderStatus::InProgress, $this->customer, $this->employee);

    $result = $this->service->markReadyForDelivery($order, $this->employee);

    $result->refresh();
    expect($result->status)->toBe(OrderStatus::ReadyForDelivery);
});
it('marks ready for delivery from ready for work', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForWork, $this->customer, $this->employee);

    $result = $this->service->markReadyForDelivery($order, $this->employee);

    $result->refresh();
    expect($result->status)->toBe(OrderStatus::ReadyForDelivery);
});
it('rejects ready for delivery from wrong status', function () {
    $order = createOrderInStatus(OrderStatus::Open, $this->customer, $this->employee);

    $this->expectException(InvalidArgumentException::class);

    $this->service->markReadyForDelivery($order, $this->employee);
});
it('delivers order', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForDelivery, $this->customer, $this->employee);

    $result = $this->service->deliverOrder($order, $this->employee);

    $result->refresh();
    expect($result->status)->toBe(OrderStatus::Delivered);
});
it('delivers order with an overpayment', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForDelivery, $this->customer, $this->employee);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);
    OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => true,
        'net_price' => 100.00,
    ]);
    OrderPayment::factory()->create(['order_id' => $order->id, 'amount' => 150.00, 'created_by' => $this->employee->id]);

    $result = $this->service->deliverOrder($order, $this->employee);

    $result->refresh();
    expect($result->status)->toBe(OrderStatus::Delivered);
});
it('rejects deliver from wrong status', function () {
    $order = createOrderInStatus(OrderStatus::Open, $this->customer, $this->employee);

    $this->expectException(InvalidArgumentException::class);

    $this->service->deliverOrder($order, $this->employee);
});
it('rejects delivery with a remaining balance', function () {
    $order = createOrderInStatus(OrderStatus::ReadyForDelivery, $this->customer, $this->employee);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);
    OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => true,
        'net_price' => 100.01,
    ]);
    OrderPayment::factory()->create(['order_id' => $order->id, 'amount' => 100.00, 'created_by' => $this->employee->id]);

    $this->expectException(InvalidArgumentException::class);

    $this->service->deliverOrder($order, $this->employee);
});
// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
/**
 * @return array<string, mixed>
 */
function validOrderData(User $customer, User $employee): array
{
    return [
        'customer_id' => $customer->id,
        'title' => 'Test Motor Order',
        'description' => 'Testing the motor order lifecycle',
        'priority' => OrderPriority::HIGH->value,
        'assigned_to' => $employee->id,
        'motor_info' => [
            'brand' => 'Honda',
            'liters' => '2.0',
            'year' => '2020',
            'model' => 'Civic',
            'cylinder_count' => '4',
            'down_payment' => 0,
        ],
        'items' => [
            [
                'item_type' => OrderItemType::CylinderHead->value,
                'components' => ['bolts', 'valves', 'springs'],
            ],
            [
                'item_type' => OrderItemType::EngineBlock->value,
                'components' => ['bearing_caps'],
            ],
        ],
    ];
}

function createOrderInStatus(OrderStatus $status, User $customer, User $employee): Order
{
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'assigned_to' => $employee->id,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
        'status' => $status->value,
    ]);

    // Ensure motor info exists for totals calculation
    OrderMotorInfo::create([
        'order_id' => $order->id,
    ]);

    return $order;
}

function createCatalogService(string $key, float $price): ServiceCatalog
{
    return ServiceCatalog::create([
        'service_key' => $key,
        'service_name_key' => "service_catalog.{$key}",
        'item_type' => OrderItemType::EngineBlock->value,
        'base_price' => $price,
        'tax_percentage' => 16.00,
        'requires_measurement' => false,
        'is_active' => true,
        'display_order' => 1,
    ]);
}
