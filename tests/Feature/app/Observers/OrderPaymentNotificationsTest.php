<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderPaidNotification;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends paid notification to customer and audit to admins', function () {
    Notification::fake();
    $users = paymentNotificationUsers();

    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
        'status' => OrderStatus::NotPaid->value,
    ]);

    $order->update(['status' => OrderStatus::Paid->value]);

    Notification::assertSentTo($users['customer'], OrderPaidNotification::class);
    Notification::assertSentTo($users['admin'], OrderAuditNotification::class);
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
