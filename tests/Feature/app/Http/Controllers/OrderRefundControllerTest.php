<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderRefundControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $admin;

    private User $otherAdmin;

    private User $superAdmin;

    private User $customer;

    public function test_refund_request_requires_returned_or_cancelled_order(): void
    {
        $order = $this->createOrder(OrderStatus::Delivered);

        $this->actingAs($this->employee)
            ->postJson($this->refundsUrl($order), ['amount' => '10.00', 'reason' => 'Customer request'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseCount('order_refunds', 0);
    }

    private function createOrder(OrderStatus $status, ?User $actor = null): Order
    {
        $actor ??= $this->employee;

        return Order::factory()->createQuietly([
            'customer_id' => $this->customer->id,
            'assigned_to' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => $status->value,
        ]);
    }

    private function refundsUrl(Order $order): string
    {
        return "/api/v1/orders/{$order->uuid}/refunds";
    }

    public function test_employee_can_request_a_refund_for_an_order_they_can_update(): void
    {
        $order = $this->createOrder(OrderStatus::Returned);
        $payment = OrderPayment::factory()->create([
            'order_id' => $order->id,
            'amount' => '100.00',
            'created_by' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employee)->postJson($this->refundsUrl($order), [
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
            'requested_by' => $this->employee->id,
        ]);

        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
        $this->assertSame('100.00', $order->fresh()->totalPaid());
    }

    public function test_customer_cannot_request_a_refund(): void
    {
        $order = $this->createOrder(OrderStatus::Cancelled);

        $this->actingAs($this->customer)
            ->postJson($this->refundsUrl($order), ['amount' => '10.00', 'reason' => 'Customer request'])
            ->assertForbidden();
    }

    public function test_admin_cannot_approve_their_own_request_but_super_admin_can(): void
    {
        $order = $this->createOrder(OrderStatus::Cancelled, $this->admin);
        $refund = $this->requestRefund($order, $this->admin, '25.00');

        $this->actingAs($this->admin)
            ->postJson($this->approveUrl($order, $refund))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->postJson($this->approveUrl($order, $refund))
            ->assertOk()
            ->assertJsonPath('refund.status', RefundStatus::Approved->value)
            ->assertJsonPath('refund.approved_by.id', $this->superAdmin->id);
    }

    private function requestRefund(Order $order, User $requester, string $amount): OrderRefund
    {
        $response = $this->actingAs($requester)->postJson($this->refundsUrl($order), [
            'amount' => $amount,
            'reason' => 'Refund test reason',
        ]);

        $response->assertCreated();

        return OrderRefund::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    private function approveUrl(Order $order, OrderRefund $refund): string
    {
        return "{$this->refundsUrl($order)}/{$refund->uuid}/approve";
    }

    public function test_admin_request_can_be_approved_by_another_admin(): void
    {
        $order = $this->createOrder(OrderStatus::Returned, $this->admin);
        $refund = $this->requestRefund($order, $this->admin, '25.00');

        $this->actingAs($this->otherAdmin)
            ->postJson($this->approveUrl($order, $refund))
            ->assertOk()
            ->assertJsonPath('refund.status', RefundStatus::Approved->value)
            ->assertJsonPath('refund.approved_by.id', $this->otherAdmin->id);
    }

    public function test_employee_cannot_approve_a_refund(): void
    {
        $order = $this->createOrder(OrderStatus::Returned);
        $refund = $this->requestRefund($order, $this->employee, '25.00');

        $this->actingAs($this->employee)
            ->postJson($this->approveUrl($order, $refund))
            ->assertForbidden();
    }

    public function test_refund_must_be_approved_before_processing(): void
    {
        $order = $this->createOrder(OrderStatus::Returned);
        $refund = $this->requestRefund($order, $this->employee, '25.00');

        $this->actingAs($this->employee)
            ->postJson($this->processUrl($order, $refund))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'orders.refund_invalid');

        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
    }

    private function processUrl(Order $order, OrderRefund $refund): string
    {
        return "{$this->refundsUrl($order)}/{$refund->uuid}/process";
    }

    public function test_multiple_processed_refunds_cannot_exceed_received_payments(): void
    {
        $order = $this->createOrder(OrderStatus::Returned);
        OrderPayment::factory()->create([
            'order_id' => $order->id,
            'amount' => '100.00',
            'created_by' => $this->employee->id,
        ]);

        $first = $this->requestRefund($order, $this->employee, '60.00');
        $this->approveAndProcess($order, $first);

        $second = $this->requestRefund($order, $this->employee, '40.00');
        $this->approveAndProcess($order, $second);

        $excessive = $this->requestRefund($order, $this->employee, '1.00');
        $this->actingAs($this->admin)
            ->postJson($this->approveUrl($order, $excessive))
            ->assertOk();

        $this->actingAs($this->employee)
            ->postJson($this->processUrl($order, $excessive))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'orders.refund_invalid');

        $this->assertSame(RefundStatus::Approved, $excessive->fresh()->status);
        $this->assertSame(OrderStatus::Returned, $order->fresh()->status);
        $this->assertSame('100.00', $order->fresh()->totalPaid());
        $this->assertSame(2, OrderRefund::query()->where('status', RefundStatus::Processed->value)->count());
    }

    private function approveAndProcess(Order $order, OrderRefund $refund): void
    {
        $this->actingAs($this->admin)
            ->postJson($this->approveUrl($order, $refund))
            ->assertOk();

        $this->actingAs($this->employee)
            ->postJson($this->processUrl($order, $refund))
            ->assertOk()
            ->assertJsonPath('refund.status', RefundStatus::Processed->value);
    }

    public function test_rejected_refund_remains_auditable(): void
    {
        $order = $this->createOrder(OrderStatus::Cancelled);
        $refund = $this->requestRefund($order, $this->employee, '25.00');

        $this->actingAs($this->admin)
            ->postJson($this->rejectUrl($order, $refund))
            ->assertOk()
            ->assertJsonPath('refund.status', RefundStatus::Rejected->value)
            ->assertJsonPath('refund.rejected_by.id', $this->admin->id);

        $this->assertModelExists($refund->fresh());
        $this->assertNotNull($refund->fresh()->rejected_at);
    }

    private function rejectUrl(Order $order, OrderRefund $refund): string
    {
        return "{$this->refundsUrl($order)}/{$refund->uuid}/reject";
    }

    public function test_super_admin_can_approve_their_own_request(): void
    {
        $order = $this->createOrder(OrderStatus::Returned, $this->superAdmin);
        $refund = $this->requestRefund($order, $this->superAdmin, '25.00');

        $this->actingAs($this->superAdmin)
            ->postJson($this->approveUrl($order, $refund))
            ->assertOk()
            ->assertJsonPath('refund.status', RefundStatus::Approved->value);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
        ]);
        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'is_active' => true,
        ]);
        $this->otherAdmin = User::factory()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'is_active' => true,
        ]);
        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'is_active' => true,
        ]);
        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'is_active' => true,
        ]);
    }
}
