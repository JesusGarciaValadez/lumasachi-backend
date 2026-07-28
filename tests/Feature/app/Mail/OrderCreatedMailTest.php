<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\OrderCreatedMail;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks mailable is send', function () {
    // Fake notifications since OrderObserver sends a notification [[memory:6242783]]
    Notification::fake();

    // Create a customer user with the CUSTOMER role
    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'email' => 'customer@example.com',
    ]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    // Assert that the notification was sent to the customer
    Notification::assertSentTo(
        $customer,
        OrderCreatedNotification::class,
        function (OrderCreatedNotification $notification) use ($order, $customer) {
            // Verify the notification contains the correct order
            expect($notification->order->id)->toEqual($order->id);

            // Verify the notification uses mail channel
            expect($notification->via($customer))->toContain('mail');

            // Get the mail instance and verify it's configured correctly
            $mail = $notification->toMail($customer);

            // Verify it's the correct mail class
            expect($mail)->toBeInstanceOf(OrderCreatedMail::class);

            // Verify the mail has the correct order
            expect($mail->order->id)->toEqual($order->id);

            // Verify the mail is queued (implements ShouldQueue)
            expect($mail)->toBeInstanceOf(ShouldQueue::class);

            // Verify the envelope (subject and recipient)
            $envelope = $mail->envelope();
            expect($envelope->subject)->toEqual(__('mail.order_created.subject', ['uuid' => $order->uuid]));

            // Verify the mail will be sent to the correct email
            expect($mail->to[0]['address'])->toEqual($customer->email);

            return true;
        }
    );
});
it('includes order url in mailable', function () {
    // Fake notifications [[memory:6242783]]
    Notification::fake();

    // Create a customer user with the CUSTOMER role
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    Notification::assertSentTo(
        $customer,
        OrderCreatedNotification::class,
        function (OrderCreatedNotification $notification) use ($order) {
            // Get the mail instance from the notification
            $mail = $notification->toMail($notification->order->customer);

            // Verify it's the correct mail class
            expect($mail)->toBeInstanceOf(OrderCreatedMail::class);

            // Verify the mail has the correct order
            expect($mail->order->id)->toEqual($order->id);

            // Verify the mail content includes the correct view and data
            $content = $mail->content();
            expect($content->markdown)->toEqual('mail.orders.created');
            expect($content->with['order']->id)->toEqual($order->id);

            return true;
        }
    );
});
