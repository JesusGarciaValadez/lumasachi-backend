<?php

namespace Tests\Unit\database\migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class CreateUsersTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that users table exists after migration.
     */
    #[Test]
    public function it_checks_if_users_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
    }

    /**
     * Test that password_reset_tokens table exists after migration.
     */
    #[Test]
    public function it_checks_if_password_reset_tokens_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    }

    /**
     * Test that sessions table exists after migration.
     */
    #[Test]
    public function it_checks_if_sessions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    /**
     * Test if the users table has all required columns.
     */
    #[Test]
    public function it_checks_if_users_table_has_all_required_columns(): void
    {
        $expectedColumns = [
            'id',
            'uuid',
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
    #[Test]
    public function it_checks_if_users_table_column_types(): void
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
        if (config('database.default') === 'pgsql') {
            $this->assertEquals('bool', Schema::getColumnType('users', 'is_active'));
        } else {
            $this->assertContains(Schema::getColumnType('users', 'is_active'), ['boolean', 'bool']);
        }

        // Test enum column
        $this->assertContains(Schema::getColumnType('users', 'role'), ['string', 'varchar']); // Enums are stored as strings in most databases
    }

    /**
     * Test nullable columns.
     */
    #[Test]
    public function it_checks_if_users_table_nullable_columns(): void
    {
        // Test by attempting to insert null values
        $user = User::create([
            'uuid' => Str::uuid()->toString(),
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
    #[Test]
    public function it_checks_if_users_table_required_columns(): void
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
    #[Test]
    public function it_checks_if_users_table_unique_constraints(): void
    {
        // Create a user
        User::create([
            'uuid' => Str::uuid()->toString(),
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
            'uuid' => Str::uuid()->toString(),
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
    #[Test]
    public function it_checks_if_users_table_role_enum_accepts_valid_values(): void
    {
        $roles = array_column(UserRole::cases(), 'value');

        foreach ($roles as $role) {
            $user = User::create([
                'uuid' => Str::uuid()->toString(),
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
    #[Test]
    public function it_checks_if_users_table_role_has_correct_default(): void
    {
        $user = User::create([
            'uuid' => Str::uuid()->toString(),
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
    #[Test]
    public function it_checks_if_password_reset_tokens_table_structure(): void
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
    #[Test]
    public function it_checks_if_sessions_table_structure(): void
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
    #[Test]
    public function it_checks_if_migration_can_be_rolled_back_and_rerun(): void
    {
        // Tables should exist after migration
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('sessions'));

        // Drop dependent tables first to avoid foreign key constraint issues
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('order_histories');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('categories');

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
    #[Test]
    public function it_checks_if_can_create_user_with_all_fields(): void
    {
        $user = User::create([
            'uuid' => Str::uuid()->toString(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER->value,
            'phone_number' => '+1234567890',
            'is_active' => true,
            'notes' => 'VIP customer with special requirements',
            'type' => UserType::INDIVIDUAL->value,
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
    #[Test]
    public function it_checks_if_can_create_user_with_minimal_fields(): void
    {
        $user = User::create([
            'uuid' => Str::uuid()->toString(),
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
