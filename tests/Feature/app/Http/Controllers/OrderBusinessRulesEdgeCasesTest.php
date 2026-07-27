<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

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
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderReviewedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderBusinessRulesEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $superAdministrator;

    private User $inactiveAdministrator;

    private User $employee;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    #[Test]
    public function order_creation_notifies_the_customer_and_every_active_audit_role(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->validOrderPayload());

        $response->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::AwaitingReview->value);

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
    }

    #[Test]
    public function order_creation_rejects_a_negative_down_payment(): void
    {
        $payload = $this->validOrderPayload();
        $payload['motor_info']['down_payment'] = -1;

        $this->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motor_info.down_payment']);

        $this->assertDatabaseEmpty('orders');
    }

    #[Test]
    public function customer_approval_rejects_a_negative_down_payment(): void
    {
        $this->actingAs($this->customer);
        $order = $this->createOrder(OrderStatus::AwaitingCustomerApproval);
        $item = OrderItem::factory()->received()->create([
            'order_id' => $order->id,
            'item_type' => OrderItemType::EngineBlock->value,
        ]);
        $catalog = $this->createCatalogService('wash_block', 600.00);
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

        $this->assertFalse($service->fresh()->is_authorized);
        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $order->fresh()->status);
    }

    #[Test]
    public function budget_rejects_an_item_that_belongs_to_another_order(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
        $otherOrder = $this->createOrder(OrderStatus::AwaitingReview);
        $otherItem = OrderItem::factory()->received()->create([
            'order_id' => $otherOrder->id,
            'item_type' => OrderItemType::EngineBlock->value,
        ]);
        $catalog = $this->createCatalogService('wash_block', 600.00);

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
        $this->assertSame(OrderStatus::AwaitingReview, $order->fresh()->status);
    }

    #[Test]
    public function budget_rejects_an_unreceived_item(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_type' => OrderItemType::EngineBlock->value,
            'is_received' => false,
        ]);
        $catalog = $this->createCatalogService('wash_block', 600.00);

        $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
            'services' => [[
                'order_item_id' => $item->id,
                'service_key' => $catalog->service_key,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['services.0.order_item_id']);
    }

    #[Test]
    public function budget_requires_measurement_for_catalog_services_that_need_it(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
        $item = OrderItem::factory()->received()->create([
            'order_id' => $order->id,
            'item_type' => OrderItemType::EngineBlock->value,
        ]);
        $catalog = $this->createCatalogService('deck_assembled_4cyl', 1600.00);

        $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
            'services' => [[
                'order_item_id' => $item->id,
                'service_key' => $catalog->service_key,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['services.0.measurement']);

        $this->assertDatabaseMissing('order_services', ['order_item_id' => $item->id]);
    }

    #[Test]
    public function budget_rejects_a_service_key_that_is_not_in_the_catalog(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
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
        $this->assertSame(OrderStatus::AwaitingReview, $order->fresh()->status);
    }

    #[Test]
    public function budget_rejects_a_service_for_a_different_item_type(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
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
        $this->assertSame(OrderStatus::AwaitingReview, $order->fresh()->status);
    }

    #[Test]
    public function budget_rejects_an_inactive_catalog_service(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
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
        $this->assertSame(OrderStatus::AwaitingReview, $order->fresh()->status);
    }

    #[Test]
    public function budget_rejects_a_non_array_services_payload(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);

        $this->postJson("/api/v1/orders/{$order->uuid}/budget", [
            'services' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['services']);

        $this->assertSame(OrderStatus::AwaitingReview, $order->fresh()->status);
    }

    #[Test]
    public function customer_approval_rejects_a_service_that_belongs_to_another_order(): void
    {
        $this->actingAs($this->customer);
        $order = $this->createOrder(OrderStatus::AwaitingCustomerApproval);
        $otherOrder = $this->createOrder(OrderStatus::AwaitingCustomerApproval);
        $otherService = $this->createOrderService($otherOrder, 'wash_block', isAuthorized: false);

        $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => [$otherService->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['authorized_service_ids.0']);

        $this->assertFalse($otherService->fresh()->is_authorized);
        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $order->fresh()->status);
    }

    #[Test]
    public function customer_approval_rejects_a_service_that_is_not_budgeted(): void
    {
        $this->actingAs($this->customer);
        $order = $this->createOrder(OrderStatus::AwaitingCustomerApproval);
        $service = $this->createOrderService($order, 'wash_block', isAuthorized: false);
        $service->update(['is_budgeted' => false]);

        $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => [$service->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['authorized_service_ids.0']);

        $this->assertFalse($service->fresh()->is_authorized);
    }

    #[Test]
    public function customer_approval_rejects_a_non_integer_service_id(): void
    {
        $this->actingAs($this->customer);
        $order = $this->createOrder(OrderStatus::AwaitingCustomerApproval);

        $this->postJson("/api/v1/orders/{$order->uuid}/customer-approval", [
            'authorized_service_ids' => ['invalid'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['authorized_service_ids.0']);

        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $order->fresh()->status);
    }

    #[Test]
    public function a_different_customer_cannot_submit_approval(): void
    {
        $otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'is_active' => true,
        ]);
        $order = $this->createOrder(OrderStatus::AwaitingCustomerApproval);
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

        $this->assertFalse($service->fresh()->is_authorized);
        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $order->fresh()->status);
    }

    #[Test]
    public function work_completion_rejects_a_service_that_belongs_to_another_order(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForWork);
        $otherOrder = $this->createOrder(OrderStatus::ReadyForWork);
        $otherService = $this->createOrderService($otherOrder, 'wash_block', isAuthorized: true);

        $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => [$otherService->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['completed_service_ids.0']);

        $this->assertFalse($otherService->fresh()->is_completed);
        $this->assertSame(OrderStatus::ReadyForWork, $order->fresh()->status);
    }

    #[Test]
    public function work_completion_rejects_a_service_that_is_not_authorized(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForWork);
        $unauthorizedService = $this->createOrderService($order, 'wash_block', isAuthorized: false);

        $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => [$unauthorizedService->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['completed_service_ids.0']);

        $this->assertFalse($unauthorizedService->fresh()->is_completed);
    }

    #[Test]
    public function work_completion_rejects_a_service_that_is_already_completed(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForWork);
        $completedService = $this->createOrderService($order, 'wash_block', isAuthorized: true);
        $completedService->update(['is_completed' => true]);

        $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => [$completedService->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['completed_service_ids.0']);

        $this->assertTrue($completedService->fresh()->is_completed);
        $this->assertSame(OrderStatus::ReadyForWork, $order->fresh()->status);
    }

    #[Test]
    public function work_completion_rejects_a_mixed_authorized_and_unauthorized_selection_atomically(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForWork);
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

        $this->assertFalse($authorizedService->fresh()->is_completed);
        $this->assertFalse($unauthorizedService->fresh()->is_completed);
    }

    #[Test]
    public function work_completion_rejects_duplicate_service_ids(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForWork);
        $service = $this->createOrderService($order, 'wash_block', isAuthorized: true);

        $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => [$service->id, $service->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['completed_service_ids.1']);

        $this->assertFalse($service->fresh()->is_completed);
    }

    #[Test]
    public function work_completion_rejects_a_non_integer_service_id(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForWork);

        $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => ['invalid'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['completed_service_ids.0']);

        $this->assertSame(OrderStatus::ReadyForWork, $order->fresh()->status);
    }

    #[Test]
    public function order_creation_rejects_a_component_from_a_different_item_type(): void
    {
        $payload = $this->validOrderPayload();
        $payload['items'][0]['components'] = ['valves'];

        $this->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.components.0']);

        $this->assertDatabaseEmpty('orders');
    }

    #[Test]
    public function quotation_totals_follow_budgeted_authorized_and_completed_services(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);
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
            $serviceKey => $this->createCatalogService($serviceKey, $price),
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
        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $order->status);
        $this->assertSame(3760.00, (float)$order->services()->sum('base_price'));
        $this->assertSame(4361.60, (float)$order->services()->sum('net_price'));

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
        $this->assertSame(1880.00, (float)$authorizedServices->sum('base_price'));
        $this->assertSame(2180.80, (float)$authorizedServices->sum('net_price'));
        $this->assertFalse($services->get('deck_assembled_4cyl')->fresh()->is_authorized);
        $this->assertFalse($services->get('polish_camshaft_bars')->fresh()->is_authorized);

        $this->actingAs($this->employee);
        $completedServiceIds = collect([
            'wash_block',
            'replace_cam_bearings',
        ])->map(fn(string $serviceKey): int => $services->get($serviceKey)->id)->all();

        $this->postJson("/api/v1/orders/{$order->uuid}/work-completed", [
            'completed_service_ids' => $completedServiceIds,
        ])->assertOk();

        $order->motorInfo->refresh();
        $this->assertSame(1252.80, (float)$order->motorInfo->total_cost);
        $this->assertFalse($services->get('weld_between_cylinders_qr25')->fresh()->is_completed);
    }

    #[Test]
    public function delivery_requires_the_remaining_balance_to_be_paid(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForDelivery);
        $order->motorInfo->update([
            'down_payment' => 1000.00,
            'total_cost' => 1252.80,
            'is_fully_paid' => false,
        ]);

        $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);

        $this->assertSame(OrderStatus::ReadyForDelivery, $order->fresh()->status);
        Notification::assertNotSentTo($this->customer, OrderDeliveredNotification::class);
        Notification::assertNotSentTo($this->administrator, OrderAuditNotification::class);

        $order->motorInfo->update(['down_payment' => 1252.80]);

        $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Delivered->value);
    }

    #[Test]
    public function delivery_accepts_exact_payment_overpayment_and_zero_total_orders(): void
    {
        foreach ([
                     ['total_cost' => 100.00, 'down_payment' => 100.00],
                     ['total_cost' => 100.00, 'down_payment' => 125.00],
                     ['total_cost' => 0.00, 'down_payment' => 0.00],
                 ] as $payment) {
            $order = $this->createOrder(OrderStatus::ReadyForDelivery);
            $order->motorInfo->update($payment);

            $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
                ->assertOk()
                ->assertJsonPath('order.status', OrderStatus::Delivered->value);
        }
    }

    #[Test]
    public function reviewed_to_awaiting_customer_approval_records_both_status_changes(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);

        $order->update([
            'status' => OrderStatus::Reviewed->value,
            'updated_by' => $this->employee->id,
        ]);

        $statusChanges = $order->orderHistories()
            ->where('field_changed', 'status')
            ->oldest('id')
            ->get();

        $this->assertCount(2, $statusChanges);
        $this->assertSame(OrderStatus::AwaitingReview, $statusChanges[0]->old_value);
        $this->assertSame(OrderStatus::Reviewed, $statusChanges[0]->new_value);
        $this->assertSame(OrderStatus::Reviewed, $statusChanges[1]->old_value);
        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $statusChanges[1]->new_value);
        $this->assertSame(OrderStatus::AwaitingCustomerApproval, $order->fresh()->status);
    }

    #[Test]
    public function delivery_notifies_the_customer_and_every_active_audit_role(): void
    {
        $order = $this->createOrder(OrderStatus::ReadyForDelivery);

        $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Delivered->value);

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
    }

    /**
     * @return array<string, mixed>
     */
    private function validOrderPayload(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'title' => 'Block review',
            'description' => 'Block received for review',
            'priority' => OrderPriority::NORMAL->value,
            'assigned_to' => $this->employee->id,
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

    private function createOrder(OrderStatus $status): Order
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
            'is_fully_paid' => true,
        ]);

        return $order;
    }

    private function createCatalogService(string $serviceKey, float $basePrice): ServiceCatalog
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

    private function createOrderService(
        Order $order,
        string $serviceKey,
        bool $isAuthorized,
    ): OrderService
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
}
