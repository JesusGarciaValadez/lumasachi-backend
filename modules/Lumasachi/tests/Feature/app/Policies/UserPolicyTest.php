<?php

namespace Modules\Lumasachi\Tests\Feature\app\Policies;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\UserType;
use Modules\Lumasachi\database\seeders\DatabaseSeeder;

final class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed(\Modules\Lumasachi\database\seeders\DatabaseSeeder::class);
    }

    /**
     * Test viewAny users permissions.
     */
    public function test_view_any_users_permissions()
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();

        // Only Super Admin and Admin can view any users
        $this->assertTrue($superAdmin->can('viewAny', User::class));
        $this->assertTrue($admin->can('viewAny', User::class));

        // Employees and Customers cannot view user lists
        $this->assertFalse($employee->can('viewAny', User::class));
        $this->assertFalse($customer->can('viewAny', User::class));
    }

    /**
     * Test view specific user permissions.
     */
    public function test_view_specific_user_permissions()
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee1 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $employee2 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->where('id', '!=', $employee1->id)->first();
        $customer1 = User::where('role', UserRole::CUSTOMER)->first();
        $customer2 = User::where('role', UserRole::CUSTOMER)->where('id', '!=', $customer1->id)->first();

        // Super Admin can view all users
        $this->assertTrue($superAdmin->can('view', $superAdmin)); // own profile
        $this->assertTrue($superAdmin->can('view', $admin));
        $this->assertTrue($superAdmin->can('view', $employee1));
        $this->assertTrue($superAdmin->can('view', $customer1));

        // Admin can view all users
        $this->assertTrue($admin->can('view', $superAdmin));
        $this->assertTrue($admin->can('view', $admin)); // own profile
        $this->assertTrue($admin->can('view', $employee1));
        $this->assertTrue($admin->can('view', $customer1));

        // Employee can only view their own profile
        $this->assertTrue($employee1->can('view', $employee1)); // own profile
        $this->assertFalse($employee1->can('view', $employee2));
        $this->assertFalse($employee1->can('view', $admin));
        $this->assertFalse($employee1->can('view', $customer1));

        // Customer can only view their own profile
        $this->assertTrue($customer1->can('view', $customer1)); // own profile
        $this->assertFalse($customer1->can('view', $customer2));
        $this->assertFalse($customer1->can('view', $employee1));
        $this->assertFalse($customer1->can('view', $admin));
    }

    /**
     * Test create user permissions.
     */
    public function test_create_user_permissions()
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();

        // Only Super Admin and Admin can create users
        $this->assertTrue($superAdmin->can('create', User::class));
        $this->assertTrue($admin->can('create', User::class));

        // Employees and Customers cannot create users
        $this->assertFalse($employee->can('create', User::class));
        $this->assertFalse($customer->can('create', User::class));
    }

    /**
     * Test update user permissions.
     */
    public function test_update_user_permissions()
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $anotherAdmin = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);

        // Super Admin can update any user including themselves
        $this->assertTrue($superAdmin->can('update', $superAdmin)); // own profile
        $this->assertTrue($superAdmin->can('update', $admin));
        $this->assertTrue($superAdmin->can('update', $employee));
        $this->assertTrue($superAdmin->can('update', $customer));

        // Admin can update any user including themselves
        $this->assertTrue($admin->can('update', $admin)); // own profile
        $this->assertTrue($admin->can('update', $anotherAdmin));
        $this->assertTrue($admin->can('update', $employee));
        $this->assertTrue($admin->can('update', $customer));
        $this->assertTrue($admin->can('update', $superAdmin)); // can update super admin

        // Employee can only update their own profile
        $this->assertTrue($employee->can('update', $employee)); // own profile
        $this->assertFalse($employee->can('update', $admin));
        $this->assertFalse($employee->can('update', $customer));

        // Customer can only update their own profile
        $this->assertTrue($customer->can('update', $customer)); // own profile
        $this->assertFalse($customer->can('update', $employee));
        $this->assertFalse($customer->can('update', $admin));
    }

    /**
     * Test delete user permissions.
     */
    public function test_delete_user_permissions()
    {
        $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $anotherSuperAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR]);

        // Super Admin can delete other users but not themselves
        $this->assertFalse($superAdmin->can('delete', $superAdmin)); // cannot delete self
        $this->assertTrue($superAdmin->can('delete', $anotherSuperAdmin));
        $this->assertTrue($superAdmin->can('delete', $admin));
        $this->assertTrue($superAdmin->can('delete', $employee));
        $this->assertTrue($superAdmin->can('delete', $customer));

        // Admin cannot delete any user (including other admins)
        $this->assertFalse($admin->can('delete', $admin)); // cannot delete self
        $this->assertFalse($admin->can('delete', $superAdmin));
        $this->assertFalse($admin->can('delete', $employee));
        $this->assertFalse($admin->can('delete', $customer));

        // Employee cannot delete any user
        $this->assertFalse($employee->can('delete', $employee)); // cannot delete self
        $this->assertFalse($employee->can('delete', $admin));
        $this->assertFalse($employee->can('delete', $customer));

        // Customer cannot delete any user
        $this->assertFalse($customer->can('delete', $customer)); // cannot delete self
        $this->assertFalse($customer->can('delete', $employee));
        $this->assertFalse($customer->can('delete', $admin));
    }


    /**
     * Test edge cases with inactive users.
     */
    public function test_permissions_with_inactive_users()
    {
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $activeEmployee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
        $inactiveEmployee = User::where('role', UserRole::EMPLOYEE)->where('is_active', false)->first();

        // Admin can view and update inactive users
        $this->assertTrue($admin->can('view', $inactiveEmployee));
        $this->assertTrue($admin->can('update', $inactiveEmployee));

        // Inactive user can still view and update their own profile
        $this->assertTrue($inactiveEmployee->can('view', $inactiveEmployee));
        $this->assertTrue($inactiveEmployee->can('update', $inactiveEmployee));

        // Inactive user cannot view other users
        $this->assertFalse($inactiveEmployee->can('view', $activeEmployee));
        $this->assertFalse($inactiveEmployee->can('view', $admin));

        // Active employee cannot view inactive employee
        $this->assertFalse($activeEmployee->can('view', $inactiveEmployee));
    }

    /**
     * Test permissions with different user types (Individual vs Business).
     */
    public function test_permissions_with_user_types()
    {
        $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
        $individualCustomer = User::where('role', UserRole::CUSTOMER)
            ->where('type', UserType::INDIVIDUAL)
            ->first();
        $businessCustomer = User::where('role', UserRole::CUSTOMER)
            ->where('type', UserType::BUSINESS)
            ->first();

        // Admin can manage both individual and business users
        $this->assertTrue($admin->can('view', $individualCustomer));
        $this->assertTrue($admin->can('view', $businessCustomer));
        $this->assertTrue($admin->can('update', $individualCustomer));
        $this->assertTrue($admin->can('update', $businessCustomer));

        // Individual customer cannot view business customer
        $this->assertFalse($individualCustomer->can('view', $businessCustomer));

        // Business customer cannot view individual customer
        $this->assertFalse($businessCustomer->can('view', $individualCustomer));

        // Both can view and update their own profiles
        $this->assertTrue($individualCustomer->can('view', $individualCustomer));
        $this->assertTrue($individualCustomer->can('update', $individualCustomer));
        $this->assertTrue($businessCustomer->can('view', $businessCustomer));
        $this->assertTrue($businessCustomer->can('update', $businessCustomer));
    }
}
