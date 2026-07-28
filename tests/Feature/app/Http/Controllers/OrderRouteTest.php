<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\User;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guest to login when accessing order route', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly(['customer_id' => $customer->id]);
    $response = $this->get(route('web.orders.show', [$order->uuid]));
    $response->assertRedirect('/login');
});
it('shows the typed order contract to an authorized user', function () {
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
});
it('forbids an authenticated user from viewing an unrelated order', function () {
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
});
test('the owning customer receives the approval capability', function () {
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
});
test('authorized staff receives the review capability for an order awaiting review', function () {
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
});
test('authorized staff receives work capabilities for an order ready for work', function () {
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
});
test('delivery capability requires a paid order', function () {
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
});
test('authorized staff can open order creation and customers cannot', function () {
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);

    $this->actingAs($employee)
        ->get(route('web.orders.create'))
        ->assertOk()
        ->assertInertia(fn(InertiaAssert $page) => $page->component('Orders/Create'));

    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

    $this->actingAs($customer)
        ->get(route('web.orders.create'))
        ->assertForbidden();
});
test('guests are redirected to login when accessing order creation', function () {
    $this->get(route('web.orders.create'))->assertRedirect('/login');
});
test('order creation navigation uses the server authorization capability', function () {
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
});
