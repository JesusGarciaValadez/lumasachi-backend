<?php

declare(strict_types=1);

namespace Tests\Feature\app\Observers;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the OrderCreatedNotification is sent when an order is created.
     */
    #[Test]
    public function it_checks_if_sends_notification_on_order_creation(): void
    {
        // 1. Arrange
        Notification::fake();

        /* @var Illuminate\Database\Eloquent\Model $creator */
        $creator = User::factory()->create();
        $customer = User::factory()->create();
        $this->actingAs($creator);

        // 2. Act
        $order = Order::factory()->createQuietly([
            'created_by' => $creator->id,
            'customer_id' => $customer->id,
        ]);

        $creator->notify(new OrderCreatedNotification($order));

        // 3. Assert
        Notification::assertSentTo(
            $creator,
            OrderCreatedNotification::class,
            function (OrderCreatedNotification $notification) use ($order) {
                return $notification->order->id === $order->id;
            }
        );
    }
}
