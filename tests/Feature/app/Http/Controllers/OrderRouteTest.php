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
            'notes' => 'Customer requested a delivery call.',
            'actual_completion' => now()->subHour(),
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
                ->where('notes', 'Customer requested a delivery call.')
                ->where('actual_completion', $order->actual_completion->toISOString())
                ->has('customer')
                ->has('motor_info')
                ->has('items')
                ->has('services')
                ->has('history')
                ->has('attachments')
                ->where('history.data', [])
                ->where('attachments.data', [])
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
    public function authorized_staff_receives_work_capabilities_for_an_order_ready_for_work(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $employee->id,
            'assigned_to' => $employee->id,
            'status' => OrderStatus::ReadyForWork->value,
        ]);

        $this->actingAs($employee)
            ->get(route('web.orders.show', [$order->uuid]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Show')
                ->has('capabilities', fn(InertiaAssert $capabilities) => $capabilities
                    ->where('complete_services', true)
                    ->where('mark_ready_for_delivery', true)
                    ->etc()
                )
            );
    }

    #[Test]
    public function delivery_capability_requires_a_paid_order(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $employee->id,
            'assigned_to' => $employee->id,
            'status' => OrderStatus::ReadyForDelivery->value,
        ]);
        $motorInfo = OrderMotorInfo::factory()->createQuietly([
            'order_id' => $order->id,
            'total_cost' => 100.00,
            'down_payment' => 50.00,
        ]);

        $this->actingAs($employee)
            ->get(route('web.orders.show', [$order->uuid]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->has('capabilities', fn(InertiaAssert $capabilities) => $capabilities
                    ->where('deliver_order', false)
                    ->etc()
                )
            );

        $motorInfo->update(['down_payment' => 100.00]);

        $this->get(route('web.orders.show', [$order->uuid]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->has('capabilities', fn(InertiaAssert $capabilities) => $capabilities
                    ->where('deliver_order', true)
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

    #[Test]
    public function order_creation_navigation_uses_the_server_authorization_capability(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);

        $this->actingAs($employee)
            ->get(route('web.orders.index'))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Index')
                ->where('can_create_order', true)
            );

        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

        $this->actingAs($customer)
            ->get(route('web.orders.index'))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Index')
                ->where('can_create_order', false)
            );
    }
}
