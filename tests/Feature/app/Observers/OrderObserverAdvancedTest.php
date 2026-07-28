<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderReadyForDeliveryNotification;
use App\Notifications\OrderReviewedNotification;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends reviewed notifications and auto transitions', function () {
    Notification::fake();
    $users = makeUsers();

    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
        'status' => OrderStatus::Open->value,
    ]);

    // Transition to reviewed
    $order->update(['status' => OrderStatus::Reviewed->value]);
    $order->refresh();

    Notification::assertSentTo($users['customer'], OrderReviewedNotification::class);

    // Auto-transition to awaiting customer approval
    expect($order->status->value)->toBe(OrderStatus::AwaitingCustomerApproval->value);
});
it('sends ready for delivery notification to customer', function () {
    Notification::fake();
    $users = makeUsers();

    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
        'status' => OrderStatus::InProgress->value,
    ]);

    $order->update(['status' => OrderStatus::ReadyForDelivery->value]);

    Notification::assertSentTo($users['customer'], OrderReadyForDeliveryNotification::class);
});
it('sends delivered notifications to customer and admins', function () {
    Notification::fake();
    $users = makeUsers();

    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
        'status' => OrderStatus::ReadyForDelivery->value,
    ]);

    $order->update(['status' => OrderStatus::Delivered->value]);

    Notification::assertSentTo($users['customer'], OrderDeliveredNotification::class);
    // Also admins receive audit notification, but we focus on customer delivery here.
});
/**
 * @return array{customer: User, employee: User, admin: User, super: User}
 */
function makeUsers(): array
{
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value, 'is_active' => true]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value, 'is_active' => true]);

    return compact('customer', 'employee', 'admin', 'super');
}
