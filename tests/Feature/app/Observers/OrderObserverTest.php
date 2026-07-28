<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if sends notification on order creation', function () {
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
});
