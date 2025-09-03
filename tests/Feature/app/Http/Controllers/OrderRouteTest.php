<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_guest_to_login_when_accessing_order_route(): void
    {
        $order = Order::factory()->createQuietly();
        $response = $this->get(route('orders.show', $order->uuid));
        $response->assertRedirect('/login');
    }

    #[Test]
    public function it_shows_order_details_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $order = Order::factory()->createQuietly(['customer_id' => $user->id]);

        $response = $this->get(route('orders.show', $order->uuid));
        $response->assertOk();
        $response->assertJsonFragment($order->title);
    }
}
