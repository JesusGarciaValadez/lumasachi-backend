<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

final class OrderIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_index_is_deterministically_sorted_and_paginated(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $sameCreatedAt = now()->subHour();

        $older = $this->createOrder($administrator, $customer, [
            'title' => 'Older order',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $firstAtSameTime = $this->createOrder($administrator, $customer, [
            'title' => 'First order at same time',
            'created_at' => $sameCreatedAt,
            'updated_at' => $sameCreatedAt,
        ]);
        $secondAtSameTime = $this->createOrder($administrator, $customer, [
            'title' => 'Second order at same time',
            'created_at' => $sameCreatedAt,
            'updated_at' => $sameCreatedAt,
        ]);
        for ($index = 0; $index < 8; $index++) {
            $this->createOrder($administrator, $customer, [
                'title' => "Older pagination order {$index}",
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('web.orders.index', ['per_page' => 10]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Index')
                ->where('filters.per_page', 10)
                ->where('orders.current_page', 1)
                ->where('orders.last_page', 2)
                ->where('orders.total', 11)
                ->where('orders.data.0.uuid', $secondAtSameTime->uuid)
                ->where('orders.data.1.uuid', $firstAtSameTime->uuid)
                ->has('orders.data', 10)
            );

        self::assertNotSame($older->uuid, $secondAtSameTime->uuid);
    }

    public function test_order_index_applies_flexible_title_date_and_status_filters(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $company = Company::factory()->create(['name' => 'Acme Engines']);
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);
        $assignee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'first_name' => 'Ana',
            'last_name' => 'Márquez',
        ]);
        $createdAt = now()->subHours(4);
        $matching = $this->createOrder($administrator, $customer, [
            'title' => 'Árbol motor premium',
            'assigned_to' => $assignee->id,
            'priority' => OrderPriority::HIGH->value,
            'lifecycle_status' => OrderLifecycleStatus::Reviewed->value,
            'disposition_status' => OrderDispositionStatus::Cancelled->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $item = OrderItem::factory()->createQuietly(['order_id' => $matching->id]);
        OrderService::factory()->completed()->createQuietly([
            'order_item_id' => $item->id,
            'net_price' => '100.00',
        ]);
        OrderPayment::factory()->createQuietly([
            'order_id' => $matching->id,
            'amount' => '100.00',
            'created_by' => $administrator->id,
        ]);

        $outsideDate = $this->createOrder($administrator, $customer, [
            'title' => 'Árbol motor premium outside date',
            'assigned_to' => $assignee->id,
            'priority' => OrderPriority::HIGH->value,
            'lifecycle_status' => OrderLifecycleStatus::Reviewed->value,
            'disposition_status' => OrderDispositionStatus::Cancelled->value,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $this->actingAs($administrator)
            ->get(route('web.orders.index', [
                'title' => 'arbol',
                'company_id' => $company->id,
                'assigned_to' => $assignee->id,
                'priority' => OrderPriority::HIGH->value,
                'lifecycle_status' => OrderLifecycleStatus::Reviewed->value,
                'payment_status' => OrderPaymentStatus::Paid->value,
                'disposition_status' => OrderDispositionStatus::Cancelled->value,
                'created_from' => $createdAt->toDateString(),
                'created_to' => $createdAt->toDateString(),
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->where('orders.total', 1)
                ->where('orders.data.0.uuid', $matching->uuid)
                ->where('orders.data.0.assigned_to.uuid', $assignee->uuid)
                ->where('orders.data.0.company.name', $company->name)
                ->where('filters.title', 'arbol')
                ->where('filters.created_from', $createdAt->toDateString())
                ->where('filters.created_to', $createdAt->toDateString())
                ->where('options.assignees', function (Collection $assignees) use ($assignee): bool {
                    return $assignees->contains('uuid', $assignee->uuid);
                })
            );

        self::assertNotSame($matching->uuid, $outsideDate->uuid);
    }

    public function test_employee_order_index_only_returns_orders_they_created_or_are_assigned(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

        $assigned = $this->createOrder($otherEmployee, $customer, [
            'title' => 'Assigned to employee',
            'assigned_to' => $employee->id,
        ]);
        $created = $this->createOrder($employee, $customer, [
            'title' => 'Created by employee',
            'assigned_to' => $otherEmployee->id,
        ]);
        $hidden = $this->createOrder($otherEmployee, $customer, [
            'title' => 'Hidden from employee',
            'assigned_to' => $otherEmployee->id,
        ]);

        $this->actingAs($employee)
            ->get(route('web.orders.index'))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->where('orders.total', 2)
                ->where('orders.data', function (Collection $orders) use ($assigned, $created): bool {
                    return $orders->pluck('uuid')->sort()->values()->all() === collect([$assigned->uuid, $created->uuid])->sort()->values()->all();
                })
            );

        self::assertNotSame($assigned->uuid, $hidden->uuid);
    }

    public function test_api_order_index_uses_the_same_deterministic_order_as_the_dashboard(): void
    {
        Cache::flush();
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $sameCreatedAt = now()->subHour();

        $this->createOrder($employee, $customer, [
            'title' => 'First API order at same time',
            'created_at' => $sameCreatedAt,
            'updated_at' => $sameCreatedAt,
        ]);
        $secondAtSameTime = $this->createOrder($employee, $customer, [
            'title' => 'Second API order at same time',
            'created_at' => $sameCreatedAt,
            'updated_at' => $sameCreatedAt,
        ]);

        $this->actingAs($employee)
            ->getJson(route('api.orders.index'))
            ->assertOk()
            ->assertJsonPath('0.uuid', $secondAtSameTime->uuid);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createOrder(User $createdBy, User $customer, array $overrides = []): Order
    {
        return Order::factory()->createQuietly(array_merge([
            'customer_id' => $customer->id,
            'created_by' => $createdBy->id,
            'updated_by' => $createdBy->id,
            'assigned_to' => $createdBy->id,
        ], $overrides));
    }
}
