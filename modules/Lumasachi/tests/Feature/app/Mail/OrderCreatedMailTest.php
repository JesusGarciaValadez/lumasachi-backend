<?php

namespace Modules\Lumasachi\tests\Feature\app\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Mail\OrderCreatedMail;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderCreatedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_order_url_in_mailable(): void
    {
        $order = Order::factory()->create();
        $url = route('orders.show', $order);
        $mailable = new OrderCreatedMail($order);

        $mailable->assertSeeInHtml($url);

        $textView = $mailable->content()->text;
        $text = view($textView, ['order' => $order])->render();
        $this->assertStringContainsString($url, $text);
    }
}
