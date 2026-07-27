<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_guest_to_login_when_accessing_order_route(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->createQuietly(['customer_id' => $customer->id]);
        $response = $this->get(route('web.orders.show', [$order->uuid]));
        $response->assertRedirect('/login');
    }

    #[Test]
    public function it_shows_the_typed_order_contract_to_an_authorized_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $this->actingAs($user);

        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => OrderStatus::Delivered->value,
        ]);

        OrderMotorInfo::factory()->createQuietly([
            'order_id' => $order->id,
            'down_payment' => 0,
            'total_cost' => 0,
        ]);

        $response = $this->get(route('web.orders.show', [$order->uuid]));
        $response->assertOk()->assertInertia(fn(InertiaAssert $page) => $page
            ->component('Orders/Show')
            ->has('order', fn(InertiaAssert $orderPage) => $orderPage
                ->where('uuid', $order->uuid)
                ->has('customer')
                ->has('motor_info')
                ->has('items')
                ->has('services')
                ->has('history')
                ->has('attachments')
                ->missing('password')
                ->etc()
            )
            ->has('capabilities', fn(InertiaAssert $capabilities) => $capabilities
                ->where('create_order', true)
                ->where('submit_budget', false)
                ->where('approve_services', false)
                ->where('complete_services', false)
                ->where('mark_ready_for_delivery', false)
                ->where('deliver_order', false)
            )
        );
    }

    #[Test]
    public function it_forbids_an_authenticated_user_from_viewing_an_unrelated_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($user);

        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $otherEmployee->id,
            'assigned_to' => $otherEmployee->id,
        ]);

        $this->get(route('web.orders.show', [$order->uuid]))->assertForbidden();
    }

    #[Test]
    public function the_owning_customer_receives_the_approval_capability(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'status' => OrderStatus::AwaitingCustomerApproval->value,
        ]);

        $this->actingAs($customer);

        $this->get(route('web.orders.show', [$order->uuid]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Show')
                ->has('capabilities', fn(InertiaAssert $capabilities) => $capabilities
                    ->where('approve_services', true)
                    ->etc()
                )
            );
    }

    #[Test]
    public function authorized_staff_receives_the_review_capability_for_an_order_awaiting_review(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $employee->id,
            'assigned_to' => $employee->id,
            'status' => OrderStatus::AwaitingReview->value,
        ]);

        $this->actingAs($employee)
            ->get(route('web.orders.show', [$order->uuid]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Show')
                ->has('capabilities', fn(InertiaAssert $capabilities) => $capabilities
                    ->where('submit_budget', true)
                    ->etc()
                )
            );
    }

    #[Test]
    public function authorized_staff_can_open_order_creation_and_customers_cannot(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);

        $this->actingAs($employee)
            ->get(route('web.orders.create'))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page->component('Orders/Create'));

        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

        $this->actingAs($customer)
            ->get(route('web.orders.create'))
            ->assertForbidden();
    }

    #[Test]
    public function guests_are_redirected_to_login_when_accessing_order_creation(): void
    {
        $this->get(route('web.orders.create'))->assertRedirect('/login');
    }
}
