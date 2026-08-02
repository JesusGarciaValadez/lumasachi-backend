<?php

declare(strict_types=1);

use App\Enums\OrderHistoryEventType;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('refund request requires returned or cancelled order', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Delivered, $actors['customer'], $actors['employee']);

    $this->actingAs($actors['employee'])
        ->postJson(orderRefundsUrl($order), ['amount' => '10.00', 'reason' => 'Customer request'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseCount('order_refunds', 0);
});

test('employee can request a refund for an order they can update', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Returned, $actors['customer'], $actors['employee']);
    $payment = OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => '100.00',
        'created_by' => $actors['employee']->id,
    ]);

    $response = $this->actingAs($actors['employee'])->postJson(orderRefundsUrl($order), [
        'amount' => '40.00',
        'reason' => 'Customer requested a partial refund.',
        'source_payment_uuid' => $payment->uuid,
    ]);

    $response->assertCreated()
        ->assertJsonPath('refund.status', RefundStatus::Requested->value)
        ->assertJsonPath('refund.amount', '40.00')
        ->assertJsonPath('refund.source_payment_id', $payment->id);

    $this->assertDatabaseHas('order_refunds', [
        'order_id' => $order->id,
        'source_payment_id' => $payment->id,
        'amount' => '40.00',
        'status' => RefundStatus::Requested->value,
        'requested_by' => $actors['employee']->id,
    ]);

    $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
    $this->assertSame('100.00', $order->fresh()->totalPaid());
});

test('customer cannot request a refund', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Cancelled, $actors['customer'], $actors['employee']);

    $this->actingAs($actors['customer'])
        ->postJson(orderRefundsUrl($order), ['amount' => '10.00', 'reason' => 'Customer request'])
        ->assertForbidden();
});

test('admin cannot approve their own request but super admin can', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Cancelled, $actors['customer'], $actors['admin']);
    $refund = orderRefundRequest($this, $order, $actors['admin'], '25.00');

    $this->actingAs($actors['admin'])
        ->postJson(orderRefundApproveUrl($order, $refund))
        ->assertForbidden();

    $this->actingAs($actors['superAdmin'])
        ->postJson(orderRefundApproveUrl($order, $refund))
        ->assertOk()
        ->assertJsonPath('refund.status', RefundStatus::Approved->value)
        ->assertJsonPath('refund.approved_by.id', $actors['superAdmin']->id);
});

test('admin request can be approved by another admin', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Returned, $actors['customer'], $actors['admin']);
    $refund = orderRefundRequest($this, $order, $actors['admin'], '25.00');

    $this->actingAs($actors['otherAdmin'])
        ->postJson(orderRefundApproveUrl($order, $refund))
        ->assertOk()
        ->assertJsonPath('refund.status', RefundStatus::Approved->value)
        ->assertJsonPath('refund.approved_by.id', $actors['otherAdmin']->id);
});

test('employee cannot approve a refund', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Returned, $actors['customer'], $actors['employee']);
    $refund = orderRefundRequest($this, $order, $actors['employee'], '25.00');

    $this->actingAs($actors['employee'])
        ->postJson(orderRefundApproveUrl($order, $refund))
        ->assertForbidden();
});

test('refund must be approved before processing', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Returned, $actors['customer'], $actors['employee']);
    $refund = orderRefundRequest($this, $order, $actors['employee'], '25.00');

    $this->actingAs($actors['employee'])
        ->postJson(orderRefundProcessUrl($order, $refund))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'orders.refund_invalid');

    $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
});

test('multiple processed refunds cannot exceed received payments', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Returned, $actors['customer'], $actors['employee']);
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => '100.00',
        'created_by' => $actors['employee']->id,
    ]);

    $first = orderRefundRequest($this, $order, $actors['employee'], '60.00');
    orderRefundApproveAndProcess($this, $order, $first, $actors['admin'], $actors['employee']);

    $second = orderRefundRequest($this, $order, $actors['employee'], '40.00');
    orderRefundApproveAndProcess($this, $order, $second, $actors['admin'], $actors['employee']);

    $excessive = orderRefundRequest($this, $order, $actors['employee'], '1.00');
    $this->actingAs($actors['admin'])
        ->postJson(orderRefundApproveUrl($order, $excessive))
        ->assertOk();

    $this->actingAs($actors['employee'])
        ->postJson(orderRefundProcessUrl($order, $excessive))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'orders.refund_invalid');

    $this->assertSame(RefundStatus::Approved, $excessive->fresh()->status);
    $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
    $this->assertSame('100.00', $order->fresh()->totalPaid());
    $this->assertSame(2, OrderRefund::query()->where('status', RefundStatus::Processed->value)->count());
});

test('rejected refund remains auditable', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Cancelled, $actors['customer'], $actors['employee']);
    $refund = orderRefundRequest($this, $order, $actors['employee'], '25.00');

    $this->actingAs($actors['admin'])
        ->postJson(orderRefundRejectUrl($order, $refund))
        ->assertOk()
        ->assertJsonPath('refund.status', RefundStatus::Rejected->value)
        ->assertJsonPath('refund.rejected_by.id', $actors['admin']->id);

    $this->assertModelExists($refund->fresh());
    $this->assertNotNull($refund->fresh()->rejected_at);

    $history = OrderHistory::query()
        ->where('order_id', $order->id)
        ->where('field_changed', OrderHistory::FIELD_REFUND)
        ->oldest('id')
        ->get();

    $this->assertCount(2, $history);
    $this->assertSame([
        RefundStatus::Requested,
        RefundStatus::Rejected,
    ], $history->pluck('new_value')->all());
    $this->assertTrue($history->every(
        fn(OrderHistory $entry): bool => $entry->event_type === OrderHistoryEventType::Refund
    ));
});

test('super admin can approve their own request', function (): void {
    $actors = orderRefundActors();
    $order = orderRefundCreateOrder(OrderStatus::Returned, $actors['customer'], $actors['superAdmin']);
    $refund = orderRefundRequest($this, $order, $actors['superAdmin'], '25.00');

    $this->actingAs($actors['superAdmin'])
        ->postJson(orderRefundApproveUrl($order, $refund))
        ->assertOk()
        ->assertJsonPath('refund.status', RefundStatus::Approved->value);
});

/**
 * @return array{employee: User, admin: User, otherAdmin: User, superAdmin: User, customer: User}
 */
function orderRefundActors(): array
{
    return [
        'employee' => User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
        ]),
        'admin' => User::factory()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'is_active' => true,
        ]),
        'otherAdmin' => User::factory()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'is_active' => true,
        ]),
        'superAdmin' => User::factory()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'is_active' => true,
        ]),
        'customer' => User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'is_active' => true,
        ]),
    ];
}

function orderRefundCreateOrder(OrderStatus $status, User $customer, User $actor): Order
{
    return Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'assigned_to' => $actor->id,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
        'status' => $status->value,
    ]);
}

function orderRefundsUrl(Order $order): string
{
    return "/api/v1/orders/{$order->uuid}/refunds";
}

function orderRefundRequest(TestCase $test, Order $order, User $requester, string $amount): OrderRefund
{
    $response = $test->actingAs($requester)->postJson(orderRefundsUrl($order), [
        'amount' => $amount,
        'reason' => 'Refund test reason',
    ]);

    $response->assertCreated();

    return OrderRefund::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
}

function orderRefundApproveUrl(Order $order, OrderRefund $refund): string
{
    return orderRefundsUrl($order) . "/{$refund->uuid}/approve";
}

function orderRefundProcessUrl(Order $order, OrderRefund $refund): string
{
    return orderRefundsUrl($order) . "/{$refund->uuid}/process";
}

function orderRefundApproveAndProcess(
    TestCase    $test,
    Order       $order,
    OrderRefund $refund,
    User        $approver,
    User        $processor,
): void
{
    $test->actingAs($approver)
        ->postJson(orderRefundApproveUrl($order, $refund))
        ->assertOk();

    $test->actingAs($processor)
        ->postJson(orderRefundProcessUrl($order, $refund))
        ->assertOk()
        ->assertJsonPath('refund.status', RefundStatus::Processed->value);
}

function orderRefundRejectUrl(Order $order, OrderRefund $refund): string
{
    return orderRefundsUrl($order) . "/{$refund->uuid}/reject";
}
