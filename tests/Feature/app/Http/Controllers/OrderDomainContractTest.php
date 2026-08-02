<?php

declare(strict_types=1);

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

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

test('authenticated order contract separates domain statuses', function (): void {
    $actors = orderDomainContractActors();
    $order = orderDomainContractCreateOrder(
        OrderLifecycleStatus::Delivered,
        $actors['employee'],
        $actors['customer'],
    );

    $this->actingAs($actors['employee'])
        ->getJson("/api/v1/orders/{$order->uuid}")
        ->assertOk()
        ->assertJsonPath('lifecycle_status', OrderLifecycleStatus::Delivered->value)
        ->assertJsonPath('lifecycle_status_label', 'Delivered')
        ->assertJsonPath('disposition_status', null)
        ->assertJsonPath('payment_status', 'Unpaid')
        ->assertJsonPath('payment_status_label', 'Unpaid')
        ->assertJsonPath('refunds', []);
});

test('transition persists only lifecycle status', function (): void {
    $actors = orderDomainContractActors();
    $order = orderDomainContractCreateOrder(
        OrderLifecycleStatus::Received,
        $actors['employee'],
        $actors['customer'],
    );

    $this->actingAs($actors['employee'])
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
});

test('payment history has a distinct event type and amount', function (): void {
    $actors = orderDomainContractActors();
    $order = orderDomainContractCreateOrder(
        OrderLifecycleStatus::AwaitingReview,
        $actors['employee'],
        $actors['customer'],
    );

    app(OrderPaymentService::class)->recordPayment($order, '25.00', $actors['employee']);

    $history = $order->orderHistories()->latest('id')->firstOrFail();

    $this->assertSame(OrderHistoryEventType::PaymentRecord, $history->event_type);
    $this->assertSame(OrderHistory::FIELD_PAYMENT_RECORD, $history->field_changed);
    $this->assertSame('25.00', $history->new_value);
});

/**
 * @return array{employee: User, customer: User}
 */
function orderDomainContractActors(): array
{
    $company = Company::factory()->create();

    return [
        'employee' => User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]),
        'customer' => User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
        ]),
    ];
}

function orderDomainContractCreateOrder(
    OrderLifecycleStatus $status,
    User                 $employee,
    User                 $customer,
): Order
{
    return Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'assigned_to' => $employee->id,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
        'lifecycle_status' => $status->value,
    ]);
}
