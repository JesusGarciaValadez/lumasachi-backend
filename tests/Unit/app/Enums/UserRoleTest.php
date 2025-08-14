<?php

namespace Tests\Unit\app\Enums;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Enums\UserRole;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all UserRole enum values are valid in the database.
     */
    #[Test]
    public function it_checks_if_all_user_role_enum_values_are_valid(): void
    {
        $enumValues = array_column(UserRole::cases(), 'value');

        $this->assertEquals([
            'Super Administrator',
            'Administrator',
            'Employee',
            'Customer',
        ], $enumValues);

        // Test each role can be stored
        foreach ($enumValues as $index => $role) {
            $user = User::create([
                'first_name' => 'Test',
                'last_name' => 'User' . $index,
                'email' => 'test' . $index . '@example.com',
                'password' => bcrypt('password'),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->assertNotNull($user);
            $this->assertEquals($role, $user->role->value);
        }
    }

    /**
     * Test that invalid role values are rejected.
     */
    #[Test]
    public function it_checks_if_invalid_role_values_are_rejected(): void
    {
        $this->expectException(\ValueError::class);

        User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'InvalidRole', // This should fail
            'is_active' => true,
        ]);
    }

    /**
     * Test that the default role is correctly applied.
     */
    #[Test]
    public function it_checks_if_default_role_is_employee(): void
    {
        // Create user without specifying role
        $user = User::create([
            'first_name' => 'Default',
            'last_name' => 'User',
            'email' => 'default@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::EMPLOYEE, $user->role);
        $this->assertEquals('Employee', $user->role->value);
    }

    /**
     * Test role permissions mapping.
     */
    #[Test]
    public function it_checks_if_user_role_permissions_mapping(): void
    {
        $testCases = [
            [
                'role' => UserRole::SUPER_ADMINISTRATOR,
                'expected_permissions_count' => 18,
                'must_have_permissions' => [
                    'users.delete',
                    'system.settings',
                    'system.logs',
                ],
            ],
            [
                'role' => UserRole::ADMINISTRATOR,
                'expected_permissions_count' => 13,
                'must_have_permissions' => [
                    'users.create',
                    'reports.export',
                ],
                'must_not_have_permissions' => [
                    'users.delete',
                    'system.settings',
                    'system.logs',
                ],
            ],
            [
                'role' => UserRole::EMPLOYEE,
                'expected_permissions_count' => 5,
                'must_have_permissions' => [
                    'orders.status_change',
                ],
                'must_not_have_permissions' => [
                    'users.create',
                    'reports.export',
                ],
            ],
            [
                'role' => UserRole::CUSTOMER,
                'expected_permissions_count' => 1,
                'must_have_permissions' => [
                    'orders.read',
                ],
                'must_not_have_permissions' => [
                    'orders.create',
                    'customers.read',
                ],
            ],
        ];

        foreach ($testCases as $testCase) {
            $permissions = UserRole::getPermissions($testCase['role']);

            // Check permission count
            $this->assertCount(
                $testCase['expected_permissions_count'],
                $permissions,
                "Role {$testCase['role']->value} should have {$testCase['expected_permissions_count']} permissions"
            );

            // Check must-have permissions
            if (isset($testCase['must_have_permissions'])) {
                foreach ($testCase['must_have_permissions'] as $permission) {
                    $this->assertContains(
                        $permission,
                        $permissions,
                        "Role {$testCase['role']->value} should have permission: {$permission}"
                    );
                }
            }

            // Check must-not-have permissions
            if (isset($testCase['must_not_have_permissions'])) {
                foreach ($testCase['must_not_have_permissions'] as $permission) {
                    $this->assertNotContains(
                        $permission,
                        $permissions,
                        "Role {$testCase['role']->value} should not have permission: {$permission}"
                    );
                }
            }
        }
    }

    /**
     * Test role labels.
     */
    #[Test]
    public function it_checks_if_user_role_labels(): void
    {
        $testCases = [
            ['role' => UserRole::SUPER_ADMINISTRATOR, 'expected' => 'Super Administrator'],
            ['role' => UserRole::ADMINISTRATOR, 'expected' => 'Administrator'],
            ['role' => UserRole::EMPLOYEE, 'expected' => 'Employee'],
            ['role' => UserRole::CUSTOMER, 'expected' => 'Customer'],
        ];

        foreach ($testCases as $testCase) {
            $this->assertEquals(
                $testCase['expected'],
                UserRole::getLabel($testCase['role']),
                "Role {$testCase['role']->value} should have label: {$testCase['expected']}"
            );
        }
    }

    /**
     * Test that role enum values match constants in User model.
     */
    #[Test]
    public function it_checks_if_role_enum_consistency(): void
    {
        // Get all role values from enum
        $enumRoles = array_column(UserRole::cases(), 'value');

        // Create a user for each role to ensure database accepts them
        foreach ($enumRoles as $role) {
            $created = DB::table('users')->insert([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test_' . uniqid() . '@example.com',
                'password' => bcrypt('password'),
                'role' => $role,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertTrue($created, "Failed to create user with role: {$role}");
        }
    }

    /**
     * Test role queries and filtering.
     */
    #[Test]
    public function it_checks_if_users_can_be_filtered_by_role(): void
    {
        // Create users with different roles
        $roles = [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR,
            UserRole::EMPLOYEE,
            UserRole::CUSTOMER,
        ];

        foreach ($roles as $index => $role) {
            User::create([
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
            $this->assertCount(1, $users);
            $this->assertEquals($role, $users->first()->role);
        }

        // Test counting by role
        $this->assertEquals(1, User::where('role', UserRole::CUSTOMER->value)->count());
        $this->assertEquals(1, User::where('role', UserRole::EMPLOYEE->value)->count());
        $this->assertEquals(4, User::count()); // Total users
    }
}
