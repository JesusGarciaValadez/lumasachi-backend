<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_guest_to_login_when_accessing_order_route(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->withCategories()->createQuietly(['customer_id' => $customer->id]);
        $response = $this->get(route('web.orders.show', [$order->uuid]));
        $response->assertRedirect('/login');
    }

    #[Test]
    public function it_shows_order_details_to_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $this->actingAs($user);

        $order = Order::factory()->withCategories()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $response = $this->get(route('web.orders.show', [$order->uuid]));
        $response->assertOk();
        $response->assertSee($order->title);
    }
}
