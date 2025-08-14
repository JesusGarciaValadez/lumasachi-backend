<?php

namespace Tests\Feature\app\Policies;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\User;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use PHPUnit\Framework\Attributes\Test;

final class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test viewAny policy for different user roles.
     */
    #[Test]
    public function it_checks_if_view_any_orders_permissions(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $inactiveEmployee = User::where('role', UserRole::EMPLOYEE)->where('is_active', false)->first();

        // All active users with these roles should be able to view any orders
        $this->assertTrue($superAdmin->can('viewAny', Order::class));
        $this->assertTrue($admin->can('viewAny', Order::class));
        $this->assertTrue($employee->can('viewAny', Order::class));
        $this->assertTrue($customer->can('viewAny', Order::class));

        // Even inactive employees can view any orders
        $this->assertTrue($inactiveEmployee->can('viewAny', Order::class));
    }

    /**
     * Test view policy for specific orders.
     */
    #[Test]
    public function it_checks_if_view_specific_order_permissions(): void
    {
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
        $this->assertTrue($superAdmin->can('view', $orderAssignedToEmployee));
        $this->assertTrue($superAdmin->can('view', $orderCreatedByEmployee));
        $this->assertTrue($superAdmin->can('view', $orderForCustomer));
        $this->assertTrue($superAdmin->can('view', $unrelatedOrder));

        $this->assertTrue($admin->can('view', $orderAssignedToEmployee));
        $this->assertTrue($admin->can('view', $orderCreatedByEmployee));
        $this->assertTrue($admin->can('view', $orderForCustomer));
        $this->assertTrue($admin->can('view', $unrelatedOrder));

        // Employee can view orders assigned to them or created by them
        $this->assertTrue($employee->can('view', $orderAssignedToEmployee));
        $this->assertTrue($employee->can('view', $orderCreatedByEmployee));
        $this->assertFalse($employee->can('view', $orderForCustomer));
        $this->assertFalse($employee->can('view', $unrelatedOrder));

        // Customer can only view their own orders
        $this->assertTrue($customer->can('view', $orderAssignedToEmployee));
        $this->assertTrue($customer->can('view', $orderCreatedByEmployee));
        $this->assertTrue($customer->can('view', $orderForCustomer));
        $this->assertFalse($customer->can('view', $unrelatedOrder));
    }

    /**
     * Test create order permissions.
     */
    #[Test]
    public function it_checks_if_create_order_permissions(): void
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();

        // Only Super Admin, Admin, and Employee can create orders
        $this->assertTrue($superAdmin->can('create', Order::class));
        $this->assertTrue($admin->can('create', Order::class));
        $this->assertTrue($employee->can('create', Order::class));

        // Customers cannot create orders (they must go through employees/admins)
        $this->assertFalse($customer->can('create', Order::class));
    }

    /**
     * Test update order permissions.
     */
    #[Test]
    public function it_checks_if_update_order_permissions(): void
    {
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
        $this->assertTrue($superAdmin->can('update', $orderAssignedToEmployee1));
        $this->assertTrue($superAdmin->can('update', $orderCreatedByEmployee1));
        $this->assertTrue($admin->can('update', $orderAssignedToEmployee1));
        $this->assertTrue($admin->can('update', $orderCreatedByEmployee1));

        // Employee can update orders assigned to them or created by them
        $this->assertTrue($employee1->can('update', $orderAssignedToEmployee1));
        $this->assertTrue($employee1->can('update', $orderCreatedByEmployee1));

        // Employee cannot update orders not assigned to them or created by them
        $this->assertFalse($employee2->can('update', $orderAssignedToEmployee1));

        // Customer cannot update orders
        $this->assertFalse($customer->can('update', $orderAssignedToEmployee1));
        $this->assertFalse($customer->can('update', $orderCreatedByEmployee1));
    }

    /**
     * Test delete order permissions.
     */
    #[Test]
    public function it_checks_if_delete_order_permissions(): void
    {
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
        $this->assertTrue($superAdmin->can('delete', $order));
        $this->assertFalse($admin->can('delete', $order));
        $this->assertFalse($employee->can('delete', $order));
        $this->assertFalse($customer->can('delete', $order));
    }

    /**
     * Test restore order permissions.
     */
    #[Test]
    public function it_checks_if_restore_order_permissions(): void
    {
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
        $this->assertTrue($superAdmin->can('restore', $deletedOrderAssignedToEmployee));
        $this->assertTrue($superAdmin->can('restore', $deletedOrderCreatedByEmployee));
        $this->assertTrue($admin->can('restore', $deletedOrderAssignedToEmployee));
        $this->assertTrue($admin->can('restore', $deletedOrderCreatedByEmployee));

        // Employee can restore orders assigned to them or created by them
        $this->assertTrue($employee1->can('restore', $deletedOrderAssignedToEmployee));
        $this->assertTrue($employee1->can('restore', $deletedOrderCreatedByEmployee));

        // Employee cannot restore orders not assigned to them or created by them
        $this->assertFalse($employee2->can('restore', $deletedOrderAssignedToEmployee));
        $this->assertFalse($employee2->can('restore', $deletedOrderCreatedByEmployee));

        // Customer cannot restore orders
        $this->assertFalse($customer->can('restore', $deletedOrderAssignedToEmployee));
        $this->assertFalse($customer->can('restore', $deletedOrderCreatedByEmployee));
    }

    /**
     * Test force delete order permissions.
     */
    #[Test]
    public function it_checks_if_force_delete_order_permissions(): void
    {
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
        $this->assertTrue($superAdmin->can('forceDelete', $deletedOrderAssignedToEmployee));
        $this->assertTrue($superAdmin->can('forceDelete', $deletedOrderCreatedByEmployee));
        $this->assertTrue($admin->can('forceDelete', $deletedOrderAssignedToEmployee));
        $this->assertTrue($admin->can('forceDelete', $deletedOrderCreatedByEmployee));

        // Employee can force delete orders assigned to them or created by them
        $this->assertTrue($employee1->can('forceDelete', $deletedOrderAssignedToEmployee));
        $this->assertTrue($employee1->can('forceDelete', $deletedOrderCreatedByEmployee));

        // Employee cannot force delete orders not assigned to them or created by them
        $this->assertFalse($employee2->can('forceDelete', $deletedOrderAssignedToEmployee));
        $this->assertFalse($employee2->can('forceDelete', $deletedOrderCreatedByEmployee));

        // Customer cannot force delete orders
        $this->assertFalse($customer->can('forceDelete', $deletedOrderAssignedToEmployee));
        $this->assertFalse($customer->can('forceDelete', $deletedOrderCreatedByEmployee));
    }

    /**
     * Test complex scenarios with order status transitions.
     */
    #[Test]
    public function it_checks_if_order_permissions_with_status_transitions(): void
    {
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();

        // Create an order that goes through different statuses
        $order = Order::factory()->createQuietly([
            'status' => OrderStatus::OPEN->value,
            'assigned_to' => $admin->id,
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
        ]);

        // Initially, employee cannot update unassigned order
        $this->assertFalse($employee->can('update', $order));

        // Assign order to employee
        $order->assigned_to = $employee->id;
        $order->status = OrderStatus::IN_PROGRESS->value;
        $order->save();

        // Now employee can update the order
        $this->assertTrue($employee->can('update', $order));

        // Even when order is completed, assigned employee can still update
        $order->status = OrderStatus::PAID->value;
        $order->save();
        $this->assertTrue($employee->can('update', $order));
    }
}
