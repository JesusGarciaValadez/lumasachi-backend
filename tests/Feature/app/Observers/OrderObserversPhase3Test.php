<?php

declare(strict_types=1);

namespace Tests\Feature\app\Observers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
use App\Models\User;
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderObserversPhase3Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_received_notifications_to_customer_and_admins(): void
    {
        Notification::fake();
        $users = $this->users();

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
    }

    #[Test]
    public function it_tracks_order_item_received_in_history(): void
    {
        $users = $this->users();
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
    }

    #[Test]
    public function it_tracks_order_service_status_in_history_and_audits_on_completed(): void
    {
        Notification::fake();
        $users = $this->users();
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
    }

    /**
     * @return array{customer: User, employee: User, admin: User}
     */
    private function users(): array
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value, 'is_active' => true]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);

        return compact('customer', 'employee', 'admin');
    }
}
