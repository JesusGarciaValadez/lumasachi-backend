<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentService;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('records payment without changing lifecycle status', function () {
    Notification::fake();
    $users = paymentNotificationUsers();

    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);

    app(OrderPaymentService::class)->recordPayment($order, '25.00', $users['employee']);

    expect($order->fresh()->lifecycleStatus())->toBe(OrderLifecycleStatus::Delivered)
        ->and($order->fresh()->totalPaid())->toBe('25.00');
});
/**
 * @return array{customer: User, employee: User, admin: User}
 */
function paymentNotificationUsers(): array
{
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value, 'is_active' => true]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);

    return compact('customer', 'employee', 'admin');
}
