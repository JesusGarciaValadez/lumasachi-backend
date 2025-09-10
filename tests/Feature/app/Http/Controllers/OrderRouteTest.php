<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Category;

class OrderRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_guest_to_login_when_accessing_order_route(): void
    {
        $order = Order::factory()->withCategories()->createQuietly();
        $response = $this->get(route('web.orders.show', [$order->uuid]));
        $response->assertRedirect('/login');
    }

    #[Test]
    public function it_shows_order_details_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $order = Order::factory()->withCategories()->createQuietly([
            'customer_id' => $user->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $response = $this->get(route('web.orders.show', [$order->uuid]));
        $response->assertOk();
        $response->assertSee($order->title);
    }
}
