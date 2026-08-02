<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('checks if all user role enum values are valid', function () {
    $enumValues = array_column(UserRole::cases(), 'value');

    expect($enumValues)->toEqual([
        'Super Administrator',
        'Administrator',
        'Employee',
        'Customer',
    ]);

    // Test each role can be stored
    foreach ($enumValues as $index => $role) {
        $user = User::create([
            'uuid' => Str::uuid7()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User' . $index,
            'email' => 'test' . $index . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);

        expect($user)->not->toBeNull();
        expect($user->role->value)->toEqual($role);
    }
});
it('checks if invalid role values are rejected', function () {
    $this->expectException(ValueError::class);

    User::create([
        'uuid' => Str::uuid7()->toString(),
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'role' => 'InvalidRole', // This should fail
        'is_active' => true,
    ]);
});
it('checks if default role is employee', function () {
    // Create user without specifying role
    $user = User::create([
        'uuid' => Str::uuid7()->toString(),
        'first_name' => 'Default',
        'last_name' => 'User',
        'email' => 'default@example.com',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    expect($user->role)->toBeInstanceOf(UserRole::class);
    expect($user->role)->toEqual(UserRole::EMPLOYEE);
    expect($user->role->value)->toEqual('Employee');
});
it('checks the :dataset user role permission mapping', function (
    UserRole $role,
    int   $expectedPermissionsCount,
    array $mustHavePermissions,
    array $mustNotHavePermissions,
): void {
    $permissions = UserRole::getPermissions($role);

    expect($permissions)->toHaveCount($expectedPermissionsCount);

    foreach ($mustHavePermissions as $permission) {
        expect($permissions)->toContain($permission);
    }

    foreach ($mustNotHavePermissions as $permission) {
        expect($permissions)->not->toContain($permission);
    }
})->with([
    'super administrator' => [
        'role' => UserRole::SUPER_ADMINISTRATOR,
        'expectedPermissionsCount' => 18,
        'mustHavePermissions' => ['users.delete', 'system.settings', 'system.logs'],
        'mustNotHavePermissions' => [],
    ],
    'administrator' => [
        'role' => UserRole::ADMINISTRATOR,
        'expectedPermissionsCount' => 12,
        'mustHavePermissions' => ['reports.export'],
        'mustNotHavePermissions' => ['users.create', 'users.delete', 'system.settings', 'system.logs'],
    ],
    'employee' => [
        'role' => UserRole::EMPLOYEE,
        'expectedPermissionsCount' => 5,
        'mustHavePermissions' => ['orders.status_change'],
        'mustNotHavePermissions' => ['users.create', 'reports.export'],
    ],
    'customer' => [
        'role' => UserRole::CUSTOMER,
        'expectedPermissionsCount' => 1,
        'mustHavePermissions' => ['orders.read'],
        'mustNotHavePermissions' => ['orders.create', 'customers.read'],
    ],
]);
it('checks if user role labels', function () {
    $testCases = [
        ['role' => UserRole::SUPER_ADMINISTRATOR, 'expected' => 'Super Administrator'],
        ['role' => UserRole::ADMINISTRATOR, 'expected' => 'Administrator'],
        ['role' => UserRole::EMPLOYEE, 'expected' => 'Employee'],
        ['role' => UserRole::CUSTOMER, 'expected' => 'Customer'],
    ];

    foreach ($testCases as $testCase) {
        expect(UserRole::getLabel($testCase['role']))->toEqual($testCase['expected']);
    }
});
it('checks if role enum consistency', function () {
    // Get all role values from enum
    $enumRoles = array_column(UserRole::cases(), 'value');

    // Create a user for each role to ensure database accepts them
    foreach ($enumRoles as $role) {
        $created = DB::table('users')->insert([
            'uuid' => Str::uuid7()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($created)->toBeTrue("Failed to create user with role: {$role}");
    }
});
it('checks if users can be filtered by role', function () {
    // Create users with different roles
    $roles = [
        UserRole::SUPER_ADMINISTRATOR,
        UserRole::ADMINISTRATOR,
        UserRole::EMPLOYEE,
        UserRole::CUSTOMER,
    ];

    foreach ($roles as $index => $role) {
        User::create([
            'uuid' => Str::uuid7()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User' . $index,
            'email' => 'user' . $index . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role->value,
            'is_active' => true,
        ]);
    }

    // Test filtering by each role
    foreach ($roles as $role) {
        $users = User::where('role', $role->value)->get();
        expect($users)->toHaveCount(1);
        expect($users->first()->role)->toEqual($role);
    }

    // Test counting by role
    expect(User::where('role', UserRole::CUSTOMER->value)->count())->toEqual(1);
    expect(User::where('role', UserRole::EMPLOYEE->value)->count())->toEqual(1);
    expect(User::count())->toEqual(4);
    // Total users
});
