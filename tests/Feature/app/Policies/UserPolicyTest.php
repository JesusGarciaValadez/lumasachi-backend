<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(DatabaseSeeder::class);
});
it('can view any users permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Only Super Admin and Admin can view any users
    expect($superAdmin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('viewAny', User::class))->toBeTrue();

    // Employees and Customers cannot view user lists
    expect($employee->can('viewAny', User::class))->toBeFalse();
    expect($customer->can('viewAny', User::class))->toBeFalse();
});
it('can view specific user permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee1 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $employee2 = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->where('id', '!=', $employee1->id)->first();
    $customer1 = User::where('role', UserRole::CUSTOMER)->first();
    $customer2 = User::where('role', UserRole::CUSTOMER)->where('id', '!=', $customer1->id)->first();

    // Super Admin can view all users
    expect($superAdmin->can('view', $superAdmin))->toBeTrue();
    // own profile
    expect($superAdmin->can('view', $admin))->toBeTrue();
    expect($superAdmin->can('view', $employee1))->toBeTrue();
    expect($superAdmin->can('view', $customer1))->toBeTrue();

    // Admin can view all users
    expect($admin->can('view', $superAdmin))->toBeTrue();
    expect($admin->can('view', $admin))->toBeTrue();
    // own profile
    expect($admin->can('view', $employee1))->toBeTrue();
    expect($admin->can('view', $customer1))->toBeTrue();

    // Employee can only view their own profile
    expect($employee1->can('view', $employee1))->toBeTrue();
    // own profile
    expect($employee1->can('view', $employee2))->toBeFalse();
    expect($employee1->can('view', $admin))->toBeFalse();
    expect($employee1->can('view', $customer1))->toBeFalse();

    // Customer can only view their own profile
    expect($customer1->can('view', $customer1))->toBeTrue();
    // own profile
    expect($customer1->can('view', $customer2))->toBeFalse();
    expect($customer1->can('view', $employee1))->toBeFalse();
    expect($customer1->can('view', $admin))->toBeFalse();
});
it('can create user permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();

    // Only Super Admin and Admin can create users
    expect($superAdmin->can('create', User::class))->toBeTrue();
    expect($admin->can('create', User::class))->toBeTrue();

    // Employees and Customers cannot create users
    expect($employee->can('create', User::class))->toBeFalse();
    expect($customer->can('create', User::class))->toBeFalse();
});
it('can update user permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();
    $anotherAdmin = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);

    // Super Admin can update any user including themselves
    expect($superAdmin->can('update', $superAdmin))->toBeTrue();
    // own profile
    expect($superAdmin->can('update', $admin))->toBeTrue();
    expect($superAdmin->can('update', $employee))->toBeTrue();
    expect($superAdmin->can('update', $customer))->toBeTrue();

    // Admin can update any user including themselves
    expect($admin->can('update', $admin))->toBeTrue();
    // own profile
    expect($admin->can('update', $anotherAdmin))->toBeTrue();
    expect($admin->can('update', $employee))->toBeTrue();
    expect($admin->can('update', $customer))->toBeTrue();
    expect($admin->can('update', $superAdmin))->toBeTrue();

    // can update super admin
    // Employee can only update their own profile
    expect($employee->can('update', $employee))->toBeTrue();
    // own profile
    expect($employee->can('update', $admin))->toBeFalse();
    expect($employee->can('update', $customer))->toBeFalse();

    // Customer can only update their own profile
    expect($customer->can('update', $customer))->toBeTrue();
    // own profile
    expect($customer->can('update', $employee))->toBeFalse();
    expect($customer->can('update', $admin))->toBeFalse();
});
it('can delete user permissions', function () {
    $superAdmin = User::where('role', UserRole::SUPER_ADMINISTRATOR)->first();
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $employee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $customer = User::where('role', UserRole::CUSTOMER)->first();
    $anotherSuperAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR]);

    // Super Admin can delete other users but not themselves
    expect($superAdmin->can('delete', $superAdmin))->toBeFalse();
    // cannot delete self
    expect($superAdmin->can('delete', $anotherSuperAdmin))->toBeTrue();
    expect($superAdmin->can('delete', $admin))->toBeTrue();
    expect($superAdmin->can('delete', $employee))->toBeTrue();
    expect($superAdmin->can('delete', $customer))->toBeTrue();

    // Admin cannot delete any user (including other admins)
    expect($admin->can('delete', $admin))->toBeFalse();
    // cannot delete self
    expect($admin->can('delete', $superAdmin))->toBeFalse();
    expect($admin->can('delete', $employee))->toBeFalse();
    expect($admin->can('delete', $customer))->toBeFalse();

    // Employee cannot delete any user
    expect($employee->can('delete', $employee))->toBeFalse();
    // cannot delete self
    expect($employee->can('delete', $admin))->toBeFalse();
    expect($employee->can('delete', $customer))->toBeFalse();

    // Customer cannot delete any user
    expect($customer->can('delete', $customer))->toBeFalse();
    // cannot delete self
    expect($customer->can('delete', $employee))->toBeFalse();
    expect($customer->can('delete', $admin))->toBeFalse();
});
it('can permissions with inactive users', function () {
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $activeEmployee = User::where('role', UserRole::EMPLOYEE)->where('is_active', true)->first();
    $inactiveEmployee = User::where('role', UserRole::EMPLOYEE)->where('is_active', false)->first();

    // Admin can view and update inactive users
    expect($admin->can('view', $inactiveEmployee))->toBeTrue();
    expect($admin->can('update', $inactiveEmployee))->toBeTrue();

    // Inactive user can still view and update their own profile
    expect($inactiveEmployee->can('view', $inactiveEmployee))->toBeTrue();
    expect($inactiveEmployee->can('update', $inactiveEmployee))->toBeTrue();

    // Inactive user cannot view other users
    expect($inactiveEmployee->can('view', $activeEmployee))->toBeFalse();
    expect($inactiveEmployee->can('view', $admin))->toBeFalse();

    // Active employee cannot view inactive employee
    expect($activeEmployee->can('view', $inactiveEmployee))->toBeFalse();
});
it('can permissions with user types', function () {
    $admin = User::where('role', UserRole::ADMINISTRATOR)->first();
    $individualCustomer = User::where('role', UserRole::CUSTOMER)
        ->where('type', UserType::INDIVIDUAL)
        ->first();
    $businessCustomer = User::where('role', UserRole::CUSTOMER)
        ->where('type', UserType::BUSINESS)
        ->first();

    // Admin can manage both individual and business users
    expect($admin->can('view', $individualCustomer))->toBeTrue();
    expect($admin->can('view', $businessCustomer))->toBeTrue();
    expect($admin->can('update', $individualCustomer))->toBeTrue();
    expect($admin->can('update', $businessCustomer))->toBeTrue();

    // Individual customer cannot view business customer
    expect($individualCustomer->can('view', $businessCustomer))->toBeFalse();

    // Business customer cannot view individual customer
    expect($businessCustomer->can('view', $individualCustomer))->toBeFalse();

    // Both can view and update their own profiles
    expect($individualCustomer->can('view', $individualCustomer))->toBeTrue();
    expect($individualCustomer->can('update', $individualCustomer))->toBeTrue();
    expect($businessCustomer->can('view', $businessCustomer))->toBeTrue();
    expect($businessCustomer->can('update', $businessCustomer))->toBeTrue();
});
