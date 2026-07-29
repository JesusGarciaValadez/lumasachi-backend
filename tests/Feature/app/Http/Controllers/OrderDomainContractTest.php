<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers;

use App\Enums\OrderHistoryEventType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class OrderDomainContractTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $customer;

    public function test_authenticated_order_contract_separates_domain_statuses(): void
    {
        $order = $this->createOrder(OrderStatus::Delivered);

        $this->actingAs($this->employee)
            ->getJson("/api/v1/orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('lifecycle_status', OrderStatus::Delivered->value)
            ->assertJsonPath('lifecycle_status_label', 'Delivered')
            ->assertJsonPath('disposition_status', null)
            ->assertJsonPath('payment_status', 'Unpaid')
            ->assertJsonPath('payment_status_label', 'Unpaid')
            ->assertJsonPath('refunds', []);
    }

    private function createOrder(OrderStatus $status): Order
    {
        return Order::factory()->createQuietly([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'created_by' => $this->employee->id,
            'updated_by' => $this->employee->id,
            'status' => $status->value,
        ]);
    }

    public function test_transition_persists_lifecycle_status_without_removing_legacy_status(): void
    {
        $order = $this->createOrder(OrderStatus::Received);

        $this->actingAs($this->employee)
            ->postJson("/api/v1/orders/{$order->uuid}/status", [
                'status' => OrderStatus::AwaitingReview->value,
            ])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::AwaitingReview->value)
            ->assertJsonPath('order.lifecycle_status', OrderStatus::AwaitingReview->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::AwaitingReview->value,
            'lifecycle_status' => OrderStatus::AwaitingReview->value,
            'disposition_status' => null,
        ]);
    }

    public function test_payment_history_has_a_distinct_event_type_and_amount(): void
    {
        $order = $this->createOrder(OrderStatus::AwaitingReview);

        app(OrderPaymentService::class)->recordPayment($order, '25.00', $this->employee);

        $history = $order->orderHistories()->latest('id')->firstOrFail();

        $this->assertSame(OrderHistoryEventType::PaymentRecord, $history->event_type);
        $this->assertSame(OrderHistory::FIELD_PAYMENT_RECORD, $history->field_changed);
        $this->assertSame('25.00', $history->new_value);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $company = Company::factory()->create();
        $this->employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
        ]);
    }
}
