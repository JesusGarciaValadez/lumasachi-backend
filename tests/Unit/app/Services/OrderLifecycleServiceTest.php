<?php

declare(strict_types=1);

namespace Tests\Unit\app\Services;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderLifecycleService $service;

    protected Company $company;

    protected User $admin;

    protected User $employee;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    // ---------------------------------------------------------------
    // createOrderWithMotorItems
    // ---------------------------------------------------------------

    #[Test]
    public function it_creates_order_with_motor_info_and_items(): void
    {
        $data = $this->validOrderData();

        $order = $this->service->createOrderWithMotorItems($data, $this->employee);

        // Order created with correct status
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->customer->id, $order->customer_id);
        $this->assertEquals('Test Motor Order', $order->title);

        // Motor info created
        $this->assertNotNull($order->motorInfo);
        $this->assertEquals('Honda', $order->motorInfo->brand);
        $this->assertEquals('2.0', $order->motorInfo->liters);
        $this->assertEquals('2020', $order->motorInfo->year);

        // Items created
        $this->assertCount(2, $order->items);
            $cylinderHead = $order->items->firstWhere('item_type', OrderItemType::CylinderHead);
        $this->assertNotNull($cylinderHead);
        $this->assertTrue($cylinderHead->is_received);

        // Components created for cylinder head
        $this->assertGreaterThan(0, $cylinderHead->components->count());
    }

    #[Test]
    public function it_creates_order_and_transitions_to_awaiting_review(): void
    {
        $data = $this->validOrderData();

        $order = $this->service->createOrderWithMotorItems($data, $this->employee);
        $order->refresh();

            $this->assertEquals(OrderStatus::AwaitingReview, $order->status);
    }

    #[Test]
    public function it_creates_order_with_empty_motor_info(): void
    {
        $data = $this->validOrderData();
        $data['motor_info'] = [];

        $order = $this->service->createOrderWithMotorItems($data, $this->employee);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertNotNull($order->motorInfo);
    }

    #[Test]
    public function it_creates_items_without_components(): void
    {
        $data = $this->validOrderData();
        $data['items'] = [
            ['item_type' => OrderItemType::Crankshaft->value],
        ];

        $order = $this->service->createOrderWithMotorItems($data, $this->employee);

        $this->assertCount(1, $order->items);
        $this->assertCount(0, $order->items->first()->components);
    }

    // ---------------------------------------------------------------
    // submitBudget
    // ---------------------------------------------------------------

    #[Test]
    public function it_submits_budget_for_order_in_awaiting_review(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::AwaitingReview);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $catalog = $this->createCatalogService('wash_block', 600.00);

        $servicesData = [
            [
                'order_item_id' => $item->id,
                'service_key' => $catalog->service_key,
                'measurement' => null,
            ],
        ];

        $result = $this->service->submitBudget($order, $servicesData, $this->employee);

        // Service created as budgeted
        $this->assertCount(1, $result->services);
        $svc = $result->services->first();
        $this->assertTrue($svc->is_budgeted);
        $this->assertEquals(600.00, (float) $svc->base_price);
        $this->assertEquals($catalog->net_price, (float) $svc->net_price);
    }

    #[Test]
    public function it_transitions_to_reviewed_after_budget(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::AwaitingReview);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $catalog = $this->createCatalogService('wash_block', 600.00);

        $this->service->submitBudget($order, [
            ['order_item_id' => $item->id, 'service_key' => $catalog->service_key, 'measurement' => null],
        ], $this->employee);

        $order->refresh();
        // Observer auto-transitions REVIEWED → AWAITING_CUSTOMER_APPROVAL
            $this->assertEquals(OrderStatus::AwaitingCustomerApproval, $order->status);
    }

    #[Test]
    public function it_rejects_budget_for_order_not_in_awaiting_review(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::Open);

        $this->expectException(InvalidArgumentException::class);

        $this->service->submitBudget($order, [], $this->employee);
    }

    // ---------------------------------------------------------------
    // customerApproval
    // ---------------------------------------------------------------

    #[Test]
    public function it_approves_services_and_sets_down_payment(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::AwaitingCustomerApproval);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $svc = OrderService::factory()->budgeted()->create([
            'order_item_id' => $item->id,
            'base_price' => 500.00,
            'net_price' => 580.00,
        ]);

        $result = $this->service->customerApproval($order, [$svc->id], 200.00);

        $svc->refresh();
        $this->assertTrue($svc->is_authorized);

        $result->refresh();
        $this->assertEquals(200.00, (float) $result->motorInfo->down_payment);
            $this->assertEquals(OrderStatus::ReadyForWork, $result->status);
    }

    #[Test]
    public function it_rejects_approval_for_wrong_status(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::OPEN);

        $this->expectException(InvalidArgumentException::class);

        $this->service->customerApproval($order, [], null);
    }

    // ---------------------------------------------------------------
    // markWorkCompleted
    // ---------------------------------------------------------------

    #[Test]
    public function it_marks_services_as_completed(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::READY_FOR_WORK);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $svc = OrderService::factory()->budgeted()->authorized()->create([
            'order_item_id' => $item->id,
            'base_price' => 500.00,
            'net_price' => 580.00,
        ]);

        $this->service->markWorkCompleted($order, [$svc->id], $this->employee);

        $svc->refresh();
        $this->assertTrue($svc->is_completed);
    }

    #[Test]
    public function it_marks_work_completed_from_in_progress(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::IN_PROGRESS);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $svc = OrderService::factory()->budgeted()->authorized()->create([
            'order_item_id' => $item->id,
            'base_price' => 500.00,
            'net_price' => 580.00,
        ]);

        $this->service->markWorkCompleted($order, [$svc->id], $this->employee);

        $svc->refresh();
        $this->assertTrue($svc->is_completed);
    }

    #[Test]
    public function it_rejects_work_completed_for_wrong_status(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::OPEN);

        $this->expectException(InvalidArgumentException::class);

        $this->service->markWorkCompleted($order, [], $this->employee);
    }

    // ---------------------------------------------------------------
    // markReadyForDelivery
    // ---------------------------------------------------------------

    #[Test]
    public function it_marks_order_ready_for_delivery(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::IN_PROGRESS);

        $result = $this->service->markReadyForDelivery($order, $this->employee);

        $result->refresh();
            $this->assertEquals(OrderStatus::ReadyForDelivery, $result->status);
    }

    #[Test]
    public function it_marks_ready_for_delivery_from_ready_for_work(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::READY_FOR_WORK);

        $result = $this->service->markReadyForDelivery($order, $this->employee);

        $result->refresh();
            $this->assertEquals(OrderStatus::ReadyForDelivery, $result->status);
    }

    #[Test]
    public function it_rejects_ready_for_delivery_from_wrong_status(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::OPEN);

        $this->expectException(InvalidArgumentException::class);

        $this->service->markReadyForDelivery($order, $this->employee);
    }

    // ---------------------------------------------------------------
    // deliverOrder
    // ---------------------------------------------------------------

    #[Test]
    public function it_delivers_order(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::READY_FOR_DELIVERY);

        $result = $this->service->deliverOrder($order, $this->employee);

        $result->refresh();
            $this->assertEquals(OrderStatus::Delivered, $result->status);
    }

    #[Test]
    public function it_rejects_deliver_from_wrong_status(): void
    {
        $order = $this->createOrderInStatus(OrderStatus::OPEN);

        $this->expectException(InvalidArgumentException::class);

        $this->service->deliverOrder($order, $this->employee);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function validOrderData(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'title' => 'Test Motor Order',
            'description' => 'Testing the motor order lifecycle',
            'priority' => OrderPriority::HIGH->value,
            'assigned_to' => $this->employee->id,
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

    private function createOrderInStatus(OrderStatus $status): Order
    {
        $order = Order::factory()->createQuietly([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'created_by' => $this->employee->id,
            'updated_by' => $this->employee->id,
            'status' => $status->value,
        ]);

        // Ensure motor info exists for totals calculation
        OrderMotorInfo::create([
            'order_id' => $order->id,
            'down_payment' => 0,
            'total_cost' => 0,
            'is_fully_paid' => false,
        ]);

        return $order;
    }

    private function createCatalogService(string $key, float $price): ServiceCatalog
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
}
