<?php

namespace Modules\Lumasachi\tests\Unit\database\migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Modules\Lumasachi\app\Enums\UserRole;
use App\Models\User;

final class CreateUsersTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that users table exists after migration.
     */
    public function test_users_table_exists()
    {
        $this->assertTrue(Schema::hasTable('users'));
    }

    /**
     * Test that password_reset_tokens table exists after migration.
     */
    public function test_password_reset_tokens_table_exists()
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    }

    /**
     * Test that sessions table exists after migration.
     */
    public function test_sessions_table_exists()
    {
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    /**
     * Test if the users table has all required columns.
     */
    public function test_users_table_has_all_required_columns()
    {
        $expectedColumns = [
            'id',
            'first_name',
            'last_name',
            'email',
            'email_verified_at',
            'password',
            'role',
            'phone_number',
            'is_active',
            'notes',
            'type',
            'preferences',
            'remember_token',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "Column '{$column}' does not exist in users table"
            );
        }
    }

    /**
     * Test column types and properties.
     */
    public function test_users_table_column_types()
    {
        // Test string columns - PostgreSQL returns 'varchar' for string columns
        $stringColumns = ['first_name', 'last_name', 'email', 'password', 'phone_number', 'type', 'preferences', 'remember_token'];
        foreach ($stringColumns as $column) {
            $this->assertContains(
                Schema::getColumnType('users', $column),
                ['string', 'varchar'],
                "Column '{$column}' is not of type string/varchar"
            );
        }

        // Test text columns
        $this->assertEquals('text', Schema::getColumnType('users', 'notes'));

        // Test timestamp columns
        $timestampColumns = ['email_verified_at', 'created_at', 'updated_at'];
        foreach ($timestampColumns as $column) {
            $this->assertContains(
                Schema::getColumnType('users', $column),
                ['timestamp', 'datetime'],
                "Column '{$column}' is not a timestamp"
            );
        }

        // Test boolean column - PostgreSQL returns 'bool'
        $this->assertContains(Schema::getColumnType('users', 'is_active'), ['boolean', 'bool']);

        // Test enum column
        $this->assertContains(Schema::getColumnType('users', 'role'), ['string', 'varchar']); // Enums are stored as strings in most databases
    }

    /**
     * Test nullable columns.
     */
    public function test_users_table_nullable_columns()
    {
        // Test by attempting to insert null values
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Nullable',
            'email' => 'nullable@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
            // These should accept null
            'email_verified_at' => null,
            'phone_number' => null,
            'notes' => null,
            'type' => null,
            'preferences' => null,
        ]);

        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_number);
        $this->assertNull($user->notes);
        $this->assertNull($user->type);
        $this->assertNull($user->preferences);
    }

    /**
     * Test required (not nullable) columns.
     */
    public function test_users_table_required_columns()
    {
        // Test by attempting to create user without required fields
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            // Missing required fields: first_name, last_name, email, password, role, is_active
            'phone_number' => '123456789',
        ]);
    }

    /**
     * Test unique constraints.
     */
    public function test_users_table_unique_constraints()
    {
        // Create a user
        User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
        ]);

        // Try to create another user with the same email
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'john@example.com', // Same email
            'password' => bcrypt('password'),
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
        ]);
    }

    /**
     * Test that the role enum accepts all valid values.
     */
    public function test_users_table_role_enum_accepts_valid_values()
    {
        $roles = array_column(UserRole::cases(), 'value');

        foreach ($roles as $role) {
            $user = User::create([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test' . uniqid() . '@example.com',
                'password' => bcrypt('password'),
                'role' => $role,
                'is_active' => true,
            ]);

            // The role attribute is cast to UserRole enum, so we need to compare the value
            $this->assertEquals($role, $user->role->value);
            $this->assertDatabaseHas('users', [
                'email' => $user->email,
                'role' => $role,
            ]);
        }
    }

    /**
     * Test that the role enum has correct default value.
     */
    public function test_users_table_role_has_correct_default()
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            // Not specifying role to test default
        ]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::EMPLOYEE->value, $user->role->value);
    }

    /**
     * Test password_reset_tokens table structure.
     */
    public function test_password_reset_tokens_table_structure()
    {
        $this->assertTrue(Schema::hasColumns('password_reset_tokens', [
            'email',
            'token',
            'created_at',
        ]));

        // Simply test that we can insert and retrieve a token
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => 'test-token',
            'created_at' => now(),
        ]);

        $token = DB::table('password_reset_tokens')->where('email', 'test@example.com')->first();
        $this->assertNotNull($token);
        $this->assertEquals('test-token', $token->token);
    }

    /**
     * Test sessions table structure.
     */
    public function test_sessions_table_structure()
    {
        $expectedColumns = [
            'id',
            'user_id',
            'ip_address',
            'user_agent',
            'payload',
            'last_activity',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('sessions', $column),
                "Column '{$column}' does not exist in sessions table"
            );
        }

        // Test column types
        $this->assertContains(Schema::getColumnType('sessions', 'id'), ['string', 'varchar']);
        $this->assertContains(Schema::getColumnType('sessions', 'last_activity'), ['integer', 'bigint', 'int4']);
        $this->assertContains(Schema::getColumnType('sessions', 'payload'), ['text', 'longtext']);
    }

    /**
     * Test that tables can be dropped and recreated.
     */
    public function test_migration_can_be_rolled_back_and_rerun()
    {
        // Tables should exist after migration
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('sessions'));

        // Drop dependent tables first to avoid foreign key constraint issues
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('order_histories');
        Schema::dropIfExists('orders');

        // Run down method
        $migration = include database_path('migrations/0001_01_01_000000_create_users_table.php');
        $migration->down();

        // Tables should not exist
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('sessions'));

        // Run up method again
        $migration->up();

        // Tables should exist again
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    /**
     * Test creating users with all possible field combinations.
     */
    public function test_can_create_user_with_all_fields()
    {
        $user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER->value,
            'phone_number' => '+1234567890',
            'is_active' => true,
            'notes' => 'VIP customer with special requirements',
            'type' => 'premium',
            'preferences' => 'email_notifications',
            'remember_token' => 'test_token_123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => UserRole::CUSTOMER->value,
            'is_active' => true,
        ]);
    }

    /**
     * Test creating user with minimal required fields.
     */
    public function test_can_create_user_with_minimal_fields()
    {
        $user = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.smith@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        // Check that nullable fields are indeed null
        $this->assertNull($user->phone_number);
        $this->assertNull($user->notes);
    }
}
