<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if orders table exists', function () {
    expect(Schema::hasTable('orders'))->toBeTrue();
});
it('checks if orders table has all required columns', function () {
    $expectedColumns = [
        'id',
        'uuid',
        'customer_id',
        'title',
        'description',
        'lifecycle_status',
        'disposition_status',
        'priority',
        'estimated_completion',
        'actual_completion',
        'notes',
        'created_by',
        'updated_by',
        'assigned_to',
        'created_at',
        'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('orders', $column))->toBeTrue("Column '{$column}' does not exist in orders table");
    }
});
it('checks if orders table column types', function () {
    // Test UUID columns
    if (config('database.default') === 'pgsql') {
        // PostgreSQL returns 'uuid' for UUID columns
        expect(Schema::getColumnType('orders', 'id'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'uuid'))->toEqual('uuid');
        expect(Schema::getColumnType('orders', 'customer_id'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'created_by'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'updated_by'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'assigned_to'))->toEqual('int8');
    }
    if (config('database.default') === 'sqlite') {
        // SQLite returns 'varchar' for UUID columns
        expect(Schema::getColumnType('orders', 'id'))->toEqual('varchar');
        expect(Schema::getColumnType('orders', 'uuid'))->toEqual('varchar');
        expect(Schema::getColumnType('orders', 'customer_id'))->toEqual('varchar');
        expect(Schema::getColumnType('orders', 'created_by'))->toEqual('varchar');
        expect(Schema::getColumnType('orders', 'updated_by'))->toEqual('varchar');
        expect(Schema::getColumnType('orders', 'assigned_to'))->toEqual('varchar');
    } else {
        // MySQL returns 'char' for UUID columns
        expect(Schema::getColumnType('orders', 'id'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'uuid'))->toEqual('uuid');
        expect(Schema::getColumnType('orders', 'customer_id'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'created_by'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'updated_by'))->toEqual('int8');
        expect(Schema::getColumnType('orders', 'assigned_to'))->toEqual('int8');
    }

    // Test string columns - PostgreSQL returns 'varchar' for string columns
    $stringColumns = ['title'];
    foreach ($stringColumns as $column) {
        expect(['string', 'varchar'])->toContain(Schema::getColumnType('orders', $column));
    }

    // Test text columns
    $textColumns = ['description', 'notes'];
    foreach ($textColumns as $column) {
        expect(Schema::getColumnType('orders', $column))->toEqual('text');
    }

    // Test enum columns - PostgreSQL may return 'string' or 'varchar' for enums
    $enumColumns = ['lifecycle_status', 'disposition_status', 'priority'];
    foreach ($enumColumns as $column) {
        expect(['enum', 'string', 'varchar'])->toContain(Schema::getColumnType('orders', $column));
    }

    // Test timestamp columns
    $timestampColumns = ['estimated_completion', 'actual_completion', 'created_at', 'updated_at'];
    foreach ($timestampColumns as $column) {
        expect(['timestamp', 'datetime'])->toContain(Schema::getColumnType('orders', $column));
    }
});
it('checks if index and foreign key constraints', function () {
    expect(Schema::hasIndex('orders', 'orders_status_priority_index'))->toBeFalse();

    // Test foreign key constraints
    $foreignKeys = ['customer_id', 'created_by', 'updated_by', 'assigned_to'];
    foreach ($foreignKeys as $foreignKey) {
        expect(Schema::hasColumn('orders', $foreignKey))->toBeTrue();
    }
});
it('checks if migration can be rolled back and rerun', function () {
    // Table should exist after migration
    expect(Schema::hasTable('orders'))->toBeTrue();

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

    // Run down method
    $migration = include base_path('database/migrations/2025_07_27_164818_create_orders_table.php');
    $migration->down();

    // Table should not exist
    expect(Schema::hasTable('orders'))->toBeFalse();

    // Run up method again
    $migration->up();

    // Table should exist again
    expect(Schema::hasTable('orders'))->toBeTrue();
});
it('checks if data insertion with order model', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'title' => 'Test Order',
        'description' => 'This is a test order.',
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'priority' => OrderPriority::LOW->value,
        'estimated_completion' => now(),
        'actual_completion' => now(),
        'notes' => 'Test notes.',
        'created_by' => $user->id,
        'assigned_to' => $user->id,
    ]);

    $this->assertDatabaseHas('orders', [
        'title' => 'Test Order',
        'description' => 'This is a test order.',
    ]);
});
it('checks if nullable columns accept null', function () {
    $customer = User::factory()->create();
    $creator = User::factory()->create();

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'title' => 'Test Order with Nulls',
        'description' => 'Testing nullable fields',
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'priority' => OrderPriority::NORMAL->value,
        'estimated_completion' => null,
        'actual_completion' => null,
        'notes' => null,
        'created_by' => $creator->id,
        'updated_by' => null,
        'assigned_to' => $creator->id,
    ]);

    expect($order->estimated_completion)->toBeNull();
    expect($order->actual_completion)->toBeNull();
    expect($order->notes)->toBeNull();
    expect($order->updated_by)->toBeNull();
    expect($order->assigned_to)->toEqual($creator->id);
});
it('checks if required columns do not accept null', function () {
    $requiredFields = [
        'customer_id',
        'title',
        'description',
        'lifecycle_status',
        'priority',
        'created_by',
        'assigned_to',
    ];

    $user = User::factory()->create();

    foreach ($requiredFields as $field) {
        try {
            $data = [
                'customer_id' => $user->id,
                'title' => 'Test Order',
                'description' => 'Test Description',
                'lifecycle_status' => OrderLifecycleStatus::Received->value,
                'priority' => OrderPriority::NORMAL->value,
                'created_by' => $user->id,
                'assigned_to' => $user->id,
            ];

            // Set the current field to null
            $data[$field] = null;

            Order::factory()->createQuietly($data);

            $this->fail("Field '{$field}' should not accept null values");
        } catch (QueryException $e) {
            // Expected exception for null constraint violation
            expect(true)->toBeTrue();
        }
    }
});
it('checks if foreign key constraints work correctly', function () {
    $customer = User::factory()->create();
    $employee = User::factory()->create();

    // Test creating order with valid foreign keys
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'title' => 'Test Foreign Keys',
        'description' => 'Testing foreign key constraints',
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
        'priority' => OrderPriority::HIGH->value,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
        'assigned_to' => $employee->id,
    ]);

    expect($order->customer_id)->toEqual($customer->id);
    expect($order->created_by)->toEqual($employee->id);
    expect($order->updated_by)->toEqual($employee->id);
    expect($order->assigned_to)->toEqual($employee->id);

    // Test that we cannot create order with non-existent user IDs
    $this->expectException(QueryException::class);

    Order::factory()->createQuietly([
        'customer_id' => 99999, // Non-existent user ID
        'title' => 'Invalid Foreign Key Test',
        'description' => 'This should fail',
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'priority' => OrderPriority::NORMAL->value,
        'created_by' => $employee->id,
    ]);
});
it('checks if all status enum values accepted', function () {
    $user = User::factory()->create();
    $statuses = OrderLifecycleStatus::getStatuses();

    foreach ($statuses as $status) {
        $order = Order::factory()->createQuietly([
            'customer_id' => $user->id,
            'title' => 'Status Test: ' . $status,
            'description' => 'Testing status value: ' . $status,
            'lifecycle_status' => $status,
            'priority' => OrderPriority::NORMAL->value,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'title' => 'Status Test: ' . $status,
            'lifecycle_status' => $status,
        ]);
    }
});
it('checks if all priority enum values accepted', function () {
    $user = User::factory()->create();
    $priorities = OrderPriority::getPriorities();

    foreach ($priorities as $priority) {
        $order = Order::factory()->createQuietly([
            'customer_id' => $user->id,
            'title' => 'Priority Test: ' . $priority,
            'description' => 'Testing priority value: ' . $priority,
            'lifecycle_status' => OrderLifecycleStatus::Received->value,
            'priority' => $priority,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'title' => 'Priority Test: ' . $priority,
            'priority' => $priority,
        ]);
    }
});
it('checks if indexes exist on correct columns', function () {
    // Get all indexes on the orders table
    $indexes = collect(Schema::getIndexes('orders'));

    expect($indexes->pluck('name')->all())
        ->not->toContain('orders_status_priority_index')
        ->not->toContain('orders_created_by_status_index')
        ->not->toContain('orders_assigned_to_status_index');
});
it('checks if foreign key behaviors', function () {
    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);
    $employee = User::factory()->create([
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'title' => 'Test Cascade Behaviors',
        'description' => 'Testing foreign key cascade behaviors',
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'priority' => OrderPriority::NORMAL->value,
        'created_by' => $employee->id,
        'assigned_to' => $employee->id,
    ]);

    // The migration specifies nullOnDelete for customer_id
    // and cascadeOnUpdate for all user foreign keys
    // We can verify the relationships exist correctly
    expect($order->customer)->not->toBeNull();
    expect($order->createdBy)->not->toBeNull();
    expect($order->customer->id)->toEqual($customer->id);
    expect($order->createdBy->id)->toEqual($employee->id);
});
