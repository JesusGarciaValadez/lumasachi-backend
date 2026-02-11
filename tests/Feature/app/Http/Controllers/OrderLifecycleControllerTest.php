<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderLifecycleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected User $employee;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    // ---------------------------------------------------------------
    // Store order with motor info + items
    // ---------------------------------------------------------------

    #[Test]
    public function it_creates_order_with_motor_info_and_items_via_api(): void
    {
        $this->actingAs($this->employee);

        $category = Category::factory()->create([
            'created_by' => $this->employee->id,
            'updated_by' => $this->employee->id,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'title' => 'Motor Rebuild #1',
            'description' => 'Full engine rebuild',
            'priority' => OrderPriority::HIGH->value,
            'assigned_to' => $this->employee->id,
            'categories' => [$category->id],
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

        $response->assertCreated();

        // Verify DB state
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Motor Rebuild #1',
        ]);

        $order = Order::with('items.components')->firstWhere('title', 'Motor Rebuild #1');
        $this->assertNotNull($order);

        // Motor info
        $this->assertDatabaseHas('order_motor_info', [
            'order_id' => $order->id,
            'brand' => 'Toyota',
        ]);

        // Items
        $this->assertCount(2, $order->items);

        // Components
        $cylinderHead = $order->items->firstWhere('item_type', OrderItemType::CylinderHead);
        $this->assertCount(2, $cylinderHead->components);
    }

    #[Test]
    public function it_validates_required_fields_for_order_creation(): void
    {
        $this->actingAs($this->employee);

        $response = $this->postJson('/api/v1/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'title', 'description', 'priority', 'assigned_to', 'items']);
    }

    #[Test]
    public function it_validates_item_types(): void
    {
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
    }

    #[Test]
    public function it_requires_authentication_to_create_order(): void
    {
        $response = $this->postJson('/api/v1/orders', []);
        $response->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Submit Budget
    // ---------------------------------------------------------------

    #[Test]
    public function it_submits_budget_for_order(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::AwaitingReview);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $catalog = $this->createCatalogService('wash_block', 600.00);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
            'services' => [
                [
                    'order_item_id' => $item->id,
                    'service_key' => $catalog->service_key,
                    'measurement' => null,
                ],
            ],
        ]);

        $response->assertOk();

        // Order should transition through REVIEWED to AWAITING_CUSTOMER_APPROVAL
        $order->refresh();
        $this->assertEquals(OrderStatus::AwaitingCustomerApproval, $order->status);
    }

    #[Test]
    public function it_rejects_budget_for_wrong_status(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::Open);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
            'services' => [],
        ]);

        $response->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Customer Approval
    // ---------------------------------------------------------------

    #[Test]
    public function it_approves_services_via_api(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::AwaitingCustomerApproval);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $svc = OrderService::factory()->budgeted()->create([
            'order_item_id' => $item->id,
            'base_price' => 500.00,
            'net_price' => 580.00,
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => [$svc->id],
            'down_payment' => 300.00,
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::ReadyForWork, $order->status);
        $this->assertEquals(300.00, (float) $order->motorInfo->down_payment);
    }

    #[Test]
    public function it_rejects_approval_for_wrong_status(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::Open);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => [1],
        ]);

        $response->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Mark Work Completed
    // ---------------------------------------------------------------

    #[Test]
    public function it_marks_services_completed_via_api(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::ReadyForWork);
        $item = OrderItem::factory()->received()->create(['order_id' => $order->id]);
        $svc = OrderService::factory()->budgeted()->authorized()->create([
            'order_item_id' => $item->id,
            'base_price' => 500.00,
            'net_price' => 580.00,
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => [$svc->id],
        ]);

        $response->assertOk();

        $svc->refresh();
        $this->assertTrue($svc->is_completed);
    }

    // ---------------------------------------------------------------
    // Ready for Delivery
    // ---------------------------------------------------------------

    #[Test]
    public function it_marks_order_ready_for_delivery_via_api(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::InProgress);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/ready-for-delivery");

        $response->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::ReadyForDelivery, $order->status);
    }

    // ---------------------------------------------------------------
    // Deliver
    // ---------------------------------------------------------------

    #[Test]
    public function it_delivers_order_via_api(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::ReadyForDelivery);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/deliver");

        $response->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
    }

    #[Test]
    public function it_rejects_deliver_for_wrong_status(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::Open);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/deliver");

        $response->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Full lifecycle e2e
    // ---------------------------------------------------------------

    #[Test]
    public function it_completes_full_motor_order_lifecycle(): void
    {
        $this->actingAs($this->employee);

        $catalog = $this->createCatalogService('pressure_test_head', 450.00);

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
        $this->assertEquals(OrderStatus::AwaitingReview, $order->status);

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
        $this->assertEquals(OrderStatus::AwaitingCustomerApproval, $order->status);

        // Step 3: Customer approval
        $serviceId = $order->services->first()->id;
        $approvalResponse = $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => [$serviceId],
            'down_payment' => 200.00,
        ]);
        $approvalResponse->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::ReadyForWork, $order->status);

        // Step 4: Mark work completed
        $workResponse = $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => [$serviceId],
        ]);
        $workResponse->assertOk();

        // Step 5: Ready for delivery
        $readyResponse = $this->postJson("/api/v1/orders/{$order->uuid}/ready-for-delivery");
        $readyResponse->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::ReadyForDelivery, $order->status);

        // Step 6: Deliver
        $deliverResponse = $this->postJson("/api/v1/orders/{$order->uuid}/deliver");
        $deliverResponse->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
    }

    // ---------------------------------------------------------------
    // Authorization checks
    // ---------------------------------------------------------------

    #[Test]
    public function it_forbids_customer_from_submitting_budget(): void
    {
        $this->actingAs($this->customer);

        $order = $this->createOrderInStatus(OrderStatus::AwaitingReview);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
            'services' => [],
        ]);

        $response->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Show order includes motor_info, items, services
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_order_with_motor_info_items_and_services(): void
    {
        $this->actingAs($this->employee);

        $order = $this->createOrderInStatus(OrderStatus::AwaitingReview);
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
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createOrderInStatus(OrderStatus $status): Order
    {
        $order = Order::factory()->createQuietly([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'created_by' => $this->employee->id,
            'updated_by' => $this->employee->id,
            'status' => $status->value,
        ]);

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
            'item_type' => OrderItemType::CylinderHead->value,
            'base_price' => $price,
            'tax_percentage' => 16.00,
            'requires_measurement' => false,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }
}
