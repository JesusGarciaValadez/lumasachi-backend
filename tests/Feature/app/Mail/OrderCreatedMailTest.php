<?php

namespace Tests\Feature\app\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Enums\UserRole;
use App\Mail\OrderCreatedMail;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderCreatedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_mailable_is_send(): void
    {
        // Fake notifications since OrderObserver sends a notification [[memory:6242783]]
        Notification::fake();

        // Create a customer user with the CUSTOMER role
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'email' => 'customer@example.com'
        ]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // Assert that the notification was sent to the customer
        Notification::assertSentTo(
            $customer,
            OrderCreatedNotification::class,
            function (OrderCreatedNotification $notification) use ($order, $customer) {
                // Verify the notification contains the correct order
                $this->assertEquals($order->id, $notification->order->id);

                // Verify the notification uses mail channel
                $this->assertContains('mail', $notification->via($customer));

                // Get the mail instance and verify it's configured correctly
                $mail = $notification->toMail($customer);

                // Verify it's the correct mail class
                $this->assertInstanceOf(OrderCreatedMail::class, $mail);

                // Verify the mail has the correct order
                $this->assertEquals($order->id, $mail->order->id);

                // Verify the mail is queued (implements ShouldQueue)
                $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $mail);

                // Verify the envelope (subject and recipient)
                $envelope = $mail->envelope();
                $this->assertEquals('New Order Created: #' . $order->uuid, $envelope->subject);

                // Verify the mail will be sent to the correct email
                $this->assertEquals($customer->email, $mail->to[0]['address']);

                return true;
            }
        );
    }

    #[Test]
    public function it_includes_order_url_in_mailable(): void
    {
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
                $this->assertInstanceOf(OrderCreatedMail::class, $mail);

                // Verify the mail has the correct order
                $this->assertEquals($order->id, $mail->order->id);

                // Verify the mail content includes the correct view and data
                $content = $mail->content();
                $this->assertEquals('mail.orders.created', $content->markdown);
                $this->assertEquals($order->id, $content->with['order']->id);

                return true;
            }
        );
    }
}
