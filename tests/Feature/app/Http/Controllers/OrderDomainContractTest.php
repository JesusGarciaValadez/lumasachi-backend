<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers;

use App\Enums\OrderHistoryEventType;
use App\Enums\OrderLifecycleStatus;
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

    public function test_authenticated_order_contract_separates_domain_statuses(): void
    {
        $order = $this->createOrder(OrderLifecycleStatus::Delivered);

        $this->actingAs($this->employee)
            ->getJson("/api/v1/orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('lifecycle_status', OrderLifecycleStatus::Delivered->value)
            ->assertJsonPath('lifecycle_status_label', 'Delivered')
            ->assertJsonPath('disposition_status', null)
            ->assertJsonPath('payment_status', 'Unpaid')
            ->assertJsonPath('payment_status_label', 'Unpaid')
            ->assertJsonPath('refunds', []);
    }

    public function test_transition_persists_only_lifecycle_status(): void
    {
        $order = $this->createOrder(OrderLifecycleStatus::Received);

        $this->actingAs($this->employee)
            ->postJson("/api/v1/orders/{$order->uuid}/status", [
                'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
            ])
            ->assertOk()
            ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::AwaitingReview->value)
            ->assertJsonMissingPath('order.status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
            'disposition_status' => null,
        ]);
    }

    public function test_payment_history_has_a_distinct_event_type_and_amount(): void
    {
        $order = $this->createOrder(OrderLifecycleStatus::AwaitingReview);

        app(OrderPaymentService::class)->recordPayment($order, '25.00', $this->employee);

        $history = $order->orderHistories()->latest('id')->firstOrFail();

        $this->assertSame(OrderHistoryEventType::PaymentRecord, $history->event_type);
        $this->assertSame(OrderHistory::FIELD_PAYMENT_RECORD, $history->field_changed);
        $this->assertSame('25.00', $history->new_value);
    }

    private function createOrder(OrderLifecycleStatus $status): Order
    {
        return Order::factory()->createQuietly([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'created_by' => $this->employee->id,
            'updated_by' => $this->employee->id,
            'lifecycle_status' => $status->value,
        ]);
    }
}
