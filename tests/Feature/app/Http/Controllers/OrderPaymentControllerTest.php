<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMotorInfo;
use App\Models\OrderPayment;
use App\Models\OrderService;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $company = Company::factory()->create();
    $this->employee = User::factory()->create([
        'role' => UserRole::EMPLOYEE->value,
        'company_id' => $company->id,
        'is_active' => true,
    ]);
    $this->customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'is_active' => true,
    ]);

    $this->actingAs($this->employee);
});

it('reports an order with no payments as unpaid', function (): void {
    $order = paymentTestOrder();
    paymentTestService($order, 100.00);

    expect($order->totalPaid())->toBe('0.00')
        ->and($order->paymentStatus())->toBe('Unpaid')
        ->and($order->hasPendingPayment())->toBeTrue();
});

it('records partial, multiple, exact, and overpayments without editing prior rows', function (): void {
    $order = paymentTestOrder();
    paymentTestService($order, 100.00);

    $this->postJson("/api/v1/orders/{$order->uuid}/payments", ['amount' => '40.00'])
        ->assertCreated()
        ->assertJsonPath('payment.amount', '40.00')
        ->assertJsonPath('order.financials.payment_status', 'Partially Paid')
        ->assertJsonPath('order.financials.remaining_balance', '60.00');

    $this->postJson("/api/v1/orders/{$order->uuid}/payments", ['amount' => '60.00'])
        ->assertCreated()
        ->assertJsonPath('order.financials.payment_status', 'Paid')
        ->assertJsonPath('order.financials.remaining_balance', '0.00');

    $this->postJson("/api/v1/orders/{$order->uuid}/payments", ['amount' => '20.00'])
        ->assertCreated()
        ->assertJsonPath('order.financials.paid', '120.00')
        ->assertJsonPath('order.financials.remaining_balance', '0.00');

    expect(OrderPayment::where('order_id', $order->id)->count())->toBe(3)
        ->and($order->fresh()->totalPaid())->toBe('120.00');
});

it('rejects zero and more-than-two-decimal payments', function (): void {
    $order = paymentTestOrder();

    $this->postJson("/api/v1/orders/{$order->uuid}/payments", ['amount' => '0'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->postJson("/api/v1/orders/{$order->uuid}/payments", ['amount' => '1.001'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    expect(OrderPayment::where('order_id', $order->id)->count())->toBe(0);
});

it('derives payment status again when the completed total changes', function (): void {
    $order = paymentTestOrder();
    $service = paymentTestService($order, 100.00);
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => 75.00,
        'created_by' => $this->employee->id,
    ]);

    expect($order->fresh()->paymentStatus())->toBe('Partially Paid');

    $service->update(['net_price' => 50.00]);

    expect($order->fresh()->paymentStatus())->toBe('Paid')
        ->and($order->fresh()->financialTotals()['remaining_balance'])->toBe('0.00');
});

it('blocks delivery while the ledger leaves an amount due', function (): void {
    $order = paymentTestOrder(OrderStatus::ReadyForDelivery);
    paymentTestService($order, 100.00);
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => 50.00,
        'created_by' => $this->employee->id,
    ]);

    $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'amount' => 50.00,
        'created_by' => $this->employee->id,
    ]);

    $this->postJson("/api/v1/orders/{$order->uuid}/deliver")
        ->assertOk()
        ->assertJsonPath('order.status', OrderStatus::Delivered->value);
});

function paymentTestOrder(OrderStatus $status = OrderStatus::AwaitingReview): Order
{
    $order = Order::factory()->createQuietly([
        'customer_id' => test()->customer->id,
        'assigned_to' => test()->employee->id,
        'created_by' => test()->employee->id,
        'updated_by' => test()->employee->id,
        'status' => $status->value,
    ]);

    OrderMotorInfo::create(['order_id' => $order->id]);

    return $order;
}

function paymentTestService(Order $order, float $netPrice): OrderService
{
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);

    return OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_completed' => true,
        'net_price' => $netPrice,
    ]);
}
