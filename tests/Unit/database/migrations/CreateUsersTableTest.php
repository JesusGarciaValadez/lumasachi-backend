<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if users table exists', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
});
it('checks if password reset tokens table exists', function () {
    expect(Schema::hasTable('password_reset_tokens'))->toBeTrue();
});
it('checks if sessions table exists', function () {
    expect(Schema::hasTable('sessions'))->toBeTrue();
});
it('checks if users table has all required columns', function () {
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
        expect(Schema::hasColumn('users', $column))->toBeTrue("Column '{$column}' does not exist in users table");
    }
});
it('checks if users table column types', function () {
    // Test string columns - PostgreSQL returns 'varchar' for string columns
    $stringColumns = ['first_name', 'last_name', 'email', 'password', 'phone_number', 'type', 'preferences', 'remember_token'];
    foreach ($stringColumns as $column) {
        expect(['string', 'varchar'])->toContain(Schema::getColumnType('users', $column));
    }

    // Test text columns
    expect(Schema::getColumnType('users', 'notes'))->toEqual('text');

    // Test timestamp columns
    $timestampColumns = ['email_verified_at', 'created_at', 'updated_at'];
    foreach ($timestampColumns as $column) {
        expect(['timestamp', 'datetime'])->toContain(Schema::getColumnType('users', $column));
    }

    // Test boolean column - PostgreSQL returns 'bool'
    if (config('database.default') === 'pgsql') {
        expect(Schema::getColumnType('users', 'is_active'))->toEqual('bool');
    } else {
        expect(['boolean', 'bool'])->toContain(Schema::getColumnType('users', 'is_active'));
    }

    // Test enum column
    expect(['string', 'varchar'])->toContain(Schema::getColumnType('users', 'role'));
    // Enums are stored as strings in most databases
});
it('checks if users table nullable columns', function () {
    // Test by attempting to insert null values
    $user = User::create([
        'uuid' => Str::uuid7()->toString(),
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

    expect($user->email_verified_at)->toBeNull();
    expect($user->phone_number)->toBeNull();
    expect($user->notes)->toBeNull();
    expect($user->type)->toBeNull();
    expect($user->preferences)->toBeNull();
});
it('checks if users table required columns', function () {
    // Test by attempting to create user without required fields
    $this->expectException(Illuminate\Database\QueryException::class);

    User::create([
        // Missing required fields: first_name, last_name, email, password, role, is_active
        'phone_number' => '123456789',
    ]);
});
it('checks if users table unique constraints', function () {
    // Create a user
    User::create([
        'uuid' => Str::uuid7()->toString(),
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::EMPLOYEE->value,
        'is_active' => true,
    ]);

    // Try to create another user with the same email
    $this->expectException(Illuminate\Database\QueryException::class);

    User::create([
        'uuid' => Str::uuid7()->toString(),
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'john@example.com', // Same email
        'password' => bcrypt('password'),
        'role' => UserRole::EMPLOYEE->value,
        'is_active' => true,
    ]);
});
it('checks if users table role enum accepts valid values', function () {
    $roles = array_column(UserRole::cases(), 'value');

    foreach ($roles as $role) {
        $user = User::create([
            'uuid' => Str::uuid7()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);

        // The role attribute is cast to UserRole enum, so we need to compare the value
        expect($user->role->value)->toEqual($role);
        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'role' => $role,
        ]);
    }
});
it('checks if users table role has correct default', function () {
    $user = User::create([
        'uuid' => Str::uuid7()->toString(),
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'is_active' => true,
        // Not specifying role to test default
    ]);

    expect($user->role)->toBeInstanceOf(UserRole::class);
    expect($user->role->value)->toEqual(UserRole::EMPLOYEE->value);
});
it('checks if password reset tokens table structure', function () {
    expect(Schema::hasColumns('password_reset_tokens', [
        'email',
        'token',
        'created_at',
    ]))->toBeTrue();

    // Simply test that we can insert and retrieve a token
    DB::table('password_reset_tokens')->insert([
        'email' => 'test@example.com',
        'token' => 'test-token',
        'created_at' => now(),
    ]);

    $token = DB::table('password_reset_tokens')->where('email', 'test@example.com')->first();
    expect($token)->not->toBeNull();
    expect($token->token)->toEqual('test-token');
});
it('checks if sessions table structure', function () {
    $expectedColumns = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('sessions', $column))->toBeTrue("Column '{$column}' does not exist in sessions table");
    }

    // Test column types
    expect(['string', 'varchar'])->toContain(Schema::getColumnType('sessions', 'id'));
    expect(['integer', 'bigint', 'int4'])->toContain(Schema::getColumnType('sessions', 'last_activity'));
    expect(['text', 'longtext'])->toContain(Schema::getColumnType('sessions', 'payload'));
});
it('checks if migration can be rolled back and rerun', function () {
    // Tables should exist after migration
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('password_reset_tokens'))->toBeTrue();
    expect(Schema::hasTable('sessions'))->toBeTrue();

    // Drop dependent tables first to avoid foreign key constraint issues
    Schema::dropIfExists('attachments');
    Schema::dropIfExists('order_histories');

    // New dependent tables introduced by motor items architecture
    Schema::dropIfExists('order_refunds');
    Schema::dropIfExists('order_payments');
    Schema::dropIfExists('order_services');
    Schema::dropIfExists('order_item_components');
    Schema::dropIfExists('order_items');
    Schema::dropIfExists('order_motor_info');
    Schema::dropIfExists('orders');

    // Run down method
    $migration = include database_path('migrations/0001_01_01_000000_create_users_table.php');
    $migration->down();

    // Tables should not exist
    expect(Schema::hasTable('users'))->toBeFalse();
    expect(Schema::hasTable('password_reset_tokens'))->toBeFalse();
    expect(Schema::hasTable('sessions'))->toBeFalse();

    // Run up method again
    $migration->up();

    // Tables should exist again
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('password_reset_tokens'))->toBeTrue();
    expect(Schema::hasTable('sessions'))->toBeTrue();
});
it('checks if can create user with all fields', function () {
    $user = User::create([
        'uuid' => Str::uuid7()->toString(),
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
});
it('checks if can create user with minimal fields', function () {
    $user = User::create([
        'uuid' => Str::uuid7()->toString(),
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
    expect($user->phone_number)->toBeNull();
    expect($user->notes)->toBeNull();
});
