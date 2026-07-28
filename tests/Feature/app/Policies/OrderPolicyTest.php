<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(DatabaseSeeder::class);
});
it('checks if view any orders permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();
    $inactiveEmployee = User::where('role', UserRole::EMPLOYEE)->where('is_active', false)->first();

    // All active users with these roles should be able to view any orders
    expect($superAdmin->can('viewAny', Order::class))->toBeTrue();
    expect($admin->can('viewAny', Order::class))->toBeTrue();
    expect($employee->can('viewAny', Order::class))->toBeTrue();
    expect($customer->can('viewAny', Order::class))->toBeTrue();

    // Even inactive employees can view any orders
    expect($inactiveEmployee->can('viewAny', Order::class))->toBeTrue();
});
it('checks if view specific order permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Create orders with specific assignments
    $orderAssignedToEmployee = Order::factory()->createQuietly([
        'assigned_to' => $employee->id,
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);

    $orderCreatedByEmployee = Order::factory()->createQuietly([
        'assigned_to' => $employee->id,
        'customer_id' => $customer->id,
        'created_by' => $employee->id,
    ]);

    $orderForCustomer = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'assigned_to' => User::where('role', UserRole::EMPLOYEE)->where('id', '!=', $employee->id)->first()->id,
        'created_by' => $admin->id,
    ]);

    $unrelatedOrder = Order::factory()->createQuietly([
        'customer_id' => User::where('role', UserRole::CUSTOMER)->where('id', '!=', $customer->id)->first()->id,
        'assigned_to' => User::where('role', UserRole::EMPLOYEE)->where('id', '!=', $employee->id)->first()->id,
        'created_by' => $admin->id,
    ]);

    // Super Admin and Admin can view all orders
    expect($superAdmin->can('view', $orderAssignedToEmployee))->toBeTrue();
    expect($superAdmin->can('view', $orderCreatedByEmployee))->toBeTrue();
    expect($superAdmin->can('view', $orderForCustomer))->toBeTrue();
    expect($superAdmin->can('view', $unrelatedOrder))->toBeTrue();

    expect($admin->can('view', $orderAssignedToEmployee))->toBeTrue();
    expect($admin->can('view', $orderCreatedByEmployee))->toBeTrue();
    expect($admin->can('view', $orderForCustomer))->toBeTrue();
    expect($admin->can('view', $unrelatedOrder))->toBeTrue();

    // Employee can view orders assigned to them or created by them
    expect($employee->can('view', $orderAssignedToEmployee))->toBeTrue();
    expect($employee->can('view', $orderCreatedByEmployee))->toBeTrue();
    expect($employee->can('view', $orderForCustomer))->toBeFalse();
    expect($employee->can('view', $unrelatedOrder))->toBeFalse();

    // Customer can only view their own orders
    expect($customer->can('view', $orderAssignedToEmployee))->toBeTrue();
    expect($customer->can('view', $orderCreatedByEmployee))->toBeTrue();
    expect($customer->can('view', $orderForCustomer))->toBeTrue();
    expect($customer->can('view', $unrelatedOrder))->toBeFalse();
});
it('checks if create order permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Only Super Admin, Admin, and Employee can create orders
    expect($superAdmin->can('create', Order::class))->toBeTrue();
    expect($admin->can('create', Order::class))->toBeTrue();
    expect($employee->can('create', Order::class))->toBeTrue();

    // Customers cannot create orders (they must go through employees/admins)
    expect($customer->can('create', Order::class))->toBeFalse();
});
it('checks if update order permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee1 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $employee2 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->where('id', '!=', $employee1->id)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Create test orders
    $orderAssignedToEmployee1 = Order::factory()->createQuietly([
        'assigned_to' => $employee1->id,
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);

    $orderCreatedByEmployee1 = Order::factory()->createQuietly([
        'assigned_to' => $employee2->id,
        'customer_id' => $customer->id,
        'created_by' => $employee1->id,
    ]);

    // Super Admin and Admin can update any order
    expect($superAdmin->can('update', $orderAssignedToEmployee1))->toBeTrue();
    expect($superAdmin->can('update', $orderCreatedByEmployee1))->toBeTrue();
    expect($admin->can('update', $orderAssignedToEmployee1))->toBeTrue();
    expect($admin->can('update', $orderCreatedByEmployee1))->toBeTrue();

    // Employee can update orders assigned to them or created by them
    expect($employee1->can('update', $orderAssignedToEmployee1))->toBeTrue();
    expect($employee1->can('update', $orderCreatedByEmployee1))->toBeTrue();

    // Employee cannot update orders not assigned to them or created by them
    expect($employee2->can('update', $orderAssignedToEmployee1))->toBeFalse();

    // Customer cannot update orders
    expect($customer->can('update', $orderAssignedToEmployee1))->toBeFalse();
    expect($customer->can('update', $orderCreatedByEmployee1))->toBeFalse();
});
test('only the owning customer can approve order services', function () {
    $customer = User::where('role', UserRole::CUSTOMER)->first();
    $otherCustomer = User::where('role', UserRole::CUSTOMER)->where('id', '!=', $customer->id)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->first();

    $order = Order::factory()->createQuietly(['customer_id' => $customer->id]);

    expect($customer->can('approve', $order))->toBeTrue();
    expect($otherCustomer->can('approve', $order))->toBeFalse();
    expect($employee->can('approve', $order))->toBeFalse();
});
it('checks if delete order permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    $order = Order::factory()->createQuietly([
        'assigned_to' => $employee->id,
        'customer_id' => $customer->id,
        'created_by' => $employee->id,
    ]);

    // Only Super Admin can delete orders
    expect($superAdmin->can('delete', $order))->toBeTrue();
    expect($admin->can('delete', $order))->toBeFalse();
    expect($employee->can('delete', $order))->toBeFalse();
    expect($customer->can('delete', $order))->toBeFalse();
});
it('checks if restore order permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee1 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $employee2 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->where('id', '!=', $employee1->id)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Create soft-deleted orders
    $deletedOrderAssignedToEmployee = Order::factory()->createQuietly([
        'assigned_to' => $employee1->id,
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);
    $deletedOrderAssignedToEmployee->delete();

    $deletedOrderCreatedByEmployee = Order::factory()->createQuietly([
        'assigned_to' => $employee1->id,
        'customer_id' => $customer->id,
        'created_by' => $employee1->id,
    ]);
    $deletedOrderCreatedByEmployee->delete();

    // Super Admin and Admin can restore any order
    expect($superAdmin->can('restore', $deletedOrderAssignedToEmployee))->toBeTrue();
    expect($superAdmin->can('restore', $deletedOrderCreatedByEmployee))->toBeTrue();
    expect($admin->can('restore', $deletedOrderAssignedToEmployee))->toBeTrue();
    expect($admin->can('restore', $deletedOrderCreatedByEmployee))->toBeTrue();

    // Employee can restore orders assigned to them or created by them
    expect($employee1->can('restore', $deletedOrderAssignedToEmployee))->toBeTrue();
    expect($employee1->can('restore', $deletedOrderCreatedByEmployee))->toBeTrue();

    // Employee cannot restore orders not assigned to them or created by them
    expect($employee2->can('restore', $deletedOrderAssignedToEmployee))->toBeFalse();
    expect($employee2->can('restore', $deletedOrderCreatedByEmployee))->toBeFalse();

    // Customer cannot restore orders
    expect($customer->can('restore', $deletedOrderAssignedToEmployee))->toBeFalse();
    expect($customer->can('restore', $deletedOrderCreatedByEmployee))->toBeFalse();
});
it('checks if force delete order permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee1 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $employee2 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->where('id', '!=', $employee1->id)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Create soft-deleted orders
    $deletedOrderAssignedToEmployee = Order::factory()->createQuietly([
        'assigned_to' => $employee1->id,
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);
    $deletedOrderAssignedToEmployee->delete();

    $deletedOrderCreatedByEmployee = Order::factory()->createQuietly([
        'assigned_to' => $employee1->id,
        'customer_id' => $customer->id,
        'created_by' => $employee1->id,
    ]);
    $deletedOrderCreatedByEmployee->delete();

    // Super Admin and Admin can force delete any order
    expect($superAdmin->can('forceDelete', $deletedOrderAssignedToEmployee))->toBeTrue();
    expect($superAdmin->can('forceDelete', $deletedOrderCreatedByEmployee))->toBeTrue();
    expect($admin->can('forceDelete', $deletedOrderAssignedToEmployee))->toBeTrue();
    expect($admin->can('forceDelete', $deletedOrderCreatedByEmployee))->toBeTrue();

    // Employee can force delete orders assigned to them or created by them
    expect($employee1->can('forceDelete', $deletedOrderAssignedToEmployee))->toBeTrue();
    expect($employee1->can('forceDelete', $deletedOrderCreatedByEmployee))->toBeTrue();

    // Employee cannot force delete orders not assigned to them or created by them
    expect($employee2->can('forceDelete', $deletedOrderAssignedToEmployee))->toBeFalse();
    expect($employee2->can('forceDelete', $deletedOrderCreatedByEmployee))->toBeFalse();

    // Customer cannot force delete orders
    expect($customer->can('forceDelete', $deletedOrderAssignedToEmployee))->toBeFalse();
    expect($customer->can('forceDelete', $deletedOrderCreatedByEmployee))->toBeFalse();
});
it('checks if order permissions with status transitions', function () {
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Create an order that goes through different statuses
    $order = Order::factory()->createQuietly([
        'status' => OrderStatus::Open->value,
        'assigned_to' => $admin->id,
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);

    // Initially, employee cannot update unassigned order
    expect($employee->can('update', $order))->toBeFalse();

    // Assign order to employee
    $order->assigned_to = $employee->id;
    $order->status = OrderStatus::InProgress->value;
    $order->save();

    // Now employee can update the order
    expect($employee->can('update', $order))->toBeTrue();

    // Even when order is completed, assigned employee can still update
    $order->status = OrderStatus::Paid->value;
    $order->save();
    expect($employee->can('update', $order))->toBeTrue();
});
