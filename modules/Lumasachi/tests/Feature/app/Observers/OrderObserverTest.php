<?php

namespace Modules\Lumasachi\tests\Feature\app\Observers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Notifications\OrderCreatedNotification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the OrderCreatedNotification is sent when an order is created.
     *
     * @return void
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
        $order = Order::factory()->create([
            'created_by' => $creator->id,
            'customer_id' => $customer->id,
        ]);

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
