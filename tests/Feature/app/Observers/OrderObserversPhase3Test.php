<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
use App\Models\User;
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderReceivedNotification;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends received notifications to customer and admins', function () {
    Notification::fake();
    $users = phase3Users();

    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
        'status' => OrderStatus::Open->value,
    ]);

    $order->update(['status' => OrderStatus::Received->value]);

    Notification::assertSentTo($users['customer'], OrderReceivedNotification::class);

    // Admins receive audit notification
    Notification::assertSentTo($users['admin'], OrderAuditNotification::class);
});
it('tracks order item received in history', function () {
    $users = phase3Users();
    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'is_received' => false,
    ]);

    $item->update(['is_received' => true]);

    $this->assertDatabaseHas('order_histories', [
        'order_id' => $order->id,
        'field_changed' => 'item_received',
        'new_value' => 'true',
    ]);
});
it('tracks order service status in history and audits on completed', function () {
    Notification::fake();
    $users = phase3Users();
    $order = Order::factory()->createQuietly([
        'customer_id' => $users['customer']->id,
        'assigned_to' => $users['employee']->id,
        'created_by' => $users['admin']->id,
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'is_received' => true,
    ]);
    $service = OrderService::factory()->create([
        'order_item_id' => $item->id,
        'is_budgeted' => false,
        'is_authorized' => false,
        'is_completed' => false,
    ]);

    $service->update([
        'is_budgeted' => true,
        'is_authorized' => true,
        'is_completed' => true,
    ]);

    $this->assertDatabaseHas('order_histories', [
        'order_id' => $order->id,
        'field_changed' => 'service_budgeted',
        'new_value' => 'true',
    ]);
    $this->assertDatabaseHas('order_histories', [
        'order_id' => $order->id,
        'field_changed' => 'service_authorized',
        'new_value' => 'true',
    ]);
    $this->assertDatabaseHas('order_histories', [
        'order_id' => $order->id,
        'field_changed' => 'service_completed',
        'new_value' => 'true',
    ]);

    Notification::assertSentTo($users['admin'], OrderAuditNotification::class);
});
/**
 * @return array{customer: User, employee: User, admin: User}
 */
function phase3Users(): array
{
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value, 'is_active' => true]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);

    return compact('customer', 'employee', 'admin');
}
