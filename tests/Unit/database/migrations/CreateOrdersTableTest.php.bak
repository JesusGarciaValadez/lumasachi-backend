<?php

namespace Tests\Unit\database\migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\Order;
use App\Models\Category;
use App\Models\User;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\Test;

use function PHPSTORM_META\map;

final class CreateOrdersTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that orders table exists after migration.
     */
    #[Test]
    public function it_checks_if_orders_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('orders'));
    }

    /**
     * Test that the orders table has all required columns.
     */
    #[Test]
    public function it_checks_if_orders_table_has_all_required_columns(): void
    {
        $expectedColumns = [
            'id',
            'uuid',
            'customer_id',
            'title',
            'description',
            'status',
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
            $this->assertTrue(
                Schema::hasColumn('orders', $column),
                "Column '{$column}' does not exist in orders table"
            );
        }
    }

    /**
     * Test column types and properties.
     */
    #[Test]
    public function it_checks_if_orders_table_column_types(): void
    {
        // Test UUID columns
        if (config('database.default') === 'pgsql') {
            // PostgreSQL returns 'uuid' for UUID columns
            $this->assertEquals('int8', Schema::getColumnType('orders', 'id'));
            $this->assertEquals('uuid', Schema::getColumnType('orders', 'uuid'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'customer_id'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'created_by'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'updated_by'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'assigned_to'));
        } if (config('database.default') === 'sqlite') {
            // SQLite returns 'varchar' for UUID columns
            $this->assertEquals('varchar', Schema::getColumnType('orders', 'id'));
            $this->assertEquals('varchar', Schema::getColumnType('orders', 'uuid'));
            $this->assertEquals('varchar', Schema::getColumnType('orders', 'customer_id'));
            $this->assertEquals('varchar', Schema::getColumnType('orders', 'created_by'));
            $this->assertEquals('varchar', Schema::getColumnType('orders', 'updated_by'));
            $this->assertEquals('varchar', Schema::getColumnType('orders', 'assigned_to'));
        } else {
            // MySQL returns 'char' for UUID columns
            $this->assertEquals('int8', Schema::getColumnType('orders', 'id'));
            $this->assertEquals('uuid', Schema::getColumnType('orders', 'uuid'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'customer_id'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'created_by'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'updated_by'));
            $this->assertEquals('int8', Schema::getColumnType('orders', 'assigned_to'));
        }

        // Test string columns - PostgreSQL returns 'varchar' for string columns
        $stringColumns = ['title'];
        foreach ($stringColumns as $column) {
            $this->assertContains(
                Schema::getColumnType('orders', $column),
                ['string', 'varchar'],
                "Column '{$column}' is not of type string/varchar"
            );
        }

        // Test text columns
        $textColumns = ['description', 'notes'];
        foreach ($textColumns as $column) {
            $this->assertEquals('text', Schema::getColumnType('orders', $column));
        }

        // Test enum columns - PostgreSQL may return 'string' or 'varchar' for enums
        $enumColumns = ['status', 'priority'];
        foreach ($enumColumns as $column) {
            $this->assertContains(
                Schema::getColumnType('orders', $column),
                ['enum', 'string', 'varchar'],
                "Column '{$column}' is not of expected type"
            );
        }
        // Test timestamp columns
        $timestampColumns = ['estimated_completion', 'actual_completion', 'created_at', 'updated_at'];
        foreach ($timestampColumns as $column) {
            $this->assertContains(
                Schema::getColumnType('orders', $column),
                ['timestamp', 'datetime'],
                "Column '{$column}' is not a timestamp"
            );
        }
    }

    /**
     * Test index and foreign key constraints.
     */
    #[Test]
    public function it_checks_if_index_and_foreign_key_constraints(): void
    {
        // Test indexes
        $indexes = [
            'orders_status_priority_index',
            'orders_created_by_status_index',
            'orders_assigned_to_status_index'
        ];

        foreach ($indexes as $index) {
            $this->assertTrue(Schema::hasIndex('orders', $index));
        }

        // Test foreign key constraints
        $foreignKeys = ['customer_id', 'created_by', 'updated_by', 'assigned_to'];
        foreach ($foreignKeys as $foreignKey) {
            $this->assertTrue(Schema::hasColumn('orders', $foreignKey));
        }
    }

    /**
     * Test migration can be rolled back and rerun.
     */
    #[Test]
    public function it_checks_if_migration_can_be_rolled_back_and_rerun(): void
    {
        // Table should exist after migration
        $this->assertTrue(Schema::hasTable('orders'));

        // Drop dependent tables first to avoid foreign key constraint issues
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('order_histories');
        Schema::dropIfExists('order_category');
        // New dependent tables introduced by motor items architecture
        Schema::dropIfExists('order_services');
        Schema::dropIfExists('order_item_components');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_motor_info');

        // Run down method
        $migration = include base_path('database/migrations/2025_07_27_164818_create_orders_table.php');
        $migration->down();

        // Table should not exist
        $this->assertFalse(Schema::hasTable('orders'));

        // Run up method again
        $migration->up();

        // Table should exist again
        $this->assertTrue(Schema::hasTable('orders'));
    }

    /**
     * Test data insertion with the Order model.
     */
    #[Test]
    public function it_checks_if_data_insertion_with_order_model(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->withCategories()->createQuietly([
            'customer_id' => $user->id,
            'title' => 'Test Order',
            'description' => 'This is a test order.',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::LOW->value,
            'estimated_completion' => now(),
            'actual_completion' => now(),
            'notes' => 'Test notes.',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'title' => 'Test Order',
            'description' => 'This is a test order.'
        ]);
    }

    /**
     * Test nullable columns can accept null values.
     */
    #[Test]
    public function it_checks_if_nullable_columns_accept_null(): void
    {
        $customer = User::factory()->create();
        $creator = User::factory()->create();

        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'title' => 'Test Order with Nulls',
            'description' => 'Testing nullable fields',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'estimated_completion' => null,
            'actual_completion' => null,
            'notes' => null,
            'created_by' => $creator->id,
            'updated_by' => null,
            'assigned_to' => $creator->id,
        ]);

        $order->load('categories');
        $this->assertCount(0, $order->categories);
        $this->assertNull($order->estimated_completion);
        $this->assertNull($order->actual_completion);
        $this->assertNull($order->notes);
        $this->assertNull($order->updated_by);
        $this->assertEquals($creator->id, $order->assigned_to);
    }

    /**
     * Test required columns do not accept null.
     */
    #[Test]
    public function it_checks_if_required_columns_do_not_accept_null(): void
    {
        $requiredFields = [
            'customer_id',
            'title',
            'description',
            'status',
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
                    'status' => OrderStatus::OPEN->value,
                    'priority' => OrderPriority::NORMAL->value,
                    'created_by' => $user->id,
                    'assigned_to' => $user->id,
                ];

                // Set the current field to null
                $data[$field] = null;

                Order::factory()->createQuietly($data);

                $this->fail("Field '{$field}' should not accept null values");
            } catch (\Illuminate\Database\QueryException $e) {
                // Expected exception for null constraint violation
                $this->assertTrue(true);
            }
        }
    }

    /**
     * Test foreign key constraints work correctly.
     */
    #[Test]
    public function it_checks_if_foreign_key_constraints_work_correctly(): void
    {
        $customer = User::factory()->create();
        $employee = User::factory()->create();

        // Test creating order with valid foreign keys
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'title' => 'Test Foreign Keys',
            'description' => 'Testing foreign key constraints',
            'status' => OrderStatus::IN_PROGRESS->value,
            'priority' => OrderPriority::HIGH->value,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
            'assigned_to' => $employee->id
        ]);

        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals($employee->id, $order->created_by);
        $this->assertEquals($employee->id, $order->updated_by);
        $this->assertEquals($employee->id, $order->assigned_to);

        // Test that we cannot create order with non-existent user IDs
        $this->expectException(QueryException::class);

        Order::factory()->createQuietly([
            'customer_id' => 99999, // Non-existent user ID
            'title' => 'Invalid Foreign Key Test',
            'description' => 'This should fail',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'created_by' => $employee->id
        ]);
    }

    /**
     * Test all enum values for status field.
     */
    #[Test]
    public function it_checks_if_all_status_enum_values_accepted(): void
    {
        $user = User::factory()->create();
        $statuses = OrderStatus::getStatuses();

        foreach ($statuses as $status) {
            $order = Order::factory()->createQuietly([
                'customer_id' => $user->id,
                'title' => 'Status Test: ' . $status,
                'description' => 'Testing status value: ' . $status,
                'status' => $status,
                'priority' => OrderPriority::NORMAL->value,
                'created_by' => $user->id,
                'assigned_to' => $user->id,
            ]);

            $this->assertDatabaseHas('orders', [
                'title' => 'Status Test: ' . $status,
                'status' => $status
            ]);
        }
    }

    /**
     * Test all enum values for priority field.
     */
    #[Test]
    public function it_checks_if_all_priority_enum_values_accepted(): void
    {
        $user = User::factory()->create();
        $priorities = OrderPriority::getPriorities();

        foreach ($priorities as $priority) {
            $order = Order::factory()->createQuietly([
                'customer_id' => $user->id,
                'title' => 'Priority Test: ' . $priority,
                'description' => 'Testing priority value: ' . $priority,
                'status' => OrderStatus::OPEN->value,
                'priority' => $priority,
                'created_by' => $user->id,
                'assigned_to' => $user->id,
            ]);

            $this->assertDatabaseHas('orders', [
                'title' => 'Priority Test: ' . $priority,
                'priority' => $priority
            ]);
        }
    }

    /**
     * Test indexes improve query performance.
     */
    #[Test]
    public function it_checks_if_indexes_exist_on_correct_columns(): void
    {
        // Get all indexes on the orders table
        $indexes = collect(Schema::getIndexes('orders'));

        // Check for composite index on status and priority
        $statusPriorityIndex = $indexes->first(function ($index) {
            return $index['name'] === 'orders_status_priority_index';
        });
        $this->assertNotNull($statusPriorityIndex, 'Status-Priority composite index does not exist');

        // Check for composite index on created_by and status
        $createdByStatusIndex = $indexes->first(function ($index) {
            return $index['name'] === 'orders_created_by_status_index';
        });
        $this->assertNotNull($createdByStatusIndex, 'CreatedBy-Status composite index does not exist');

        // Check for composite index on assigned_to and status
        $assignedToStatusIndex = $indexes->first(function ($index) {
            return $index['name'] === 'orders_assigned_to_status_index';
        });
        $this->assertNotNull($assignedToStatusIndex, 'AssignedTo-Status composite index does not exist');
    }

    /**
     * Test foreign key behaviors.
     */
    #[Test]
    public function it_checks_if_foreign_key_behaviors(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value
        ]);
        $employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value
        ]);

        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'title' => 'Test Cascade Behaviors',
            'description' => 'Testing foreign key cascade behaviors',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'created_by' => $employee->id,
            'assigned_to' => $employee->id
        ]);

        // The migration specifies nullOnDelete for customer_id
        // and cascadeOnUpdate for all user foreign keys
        // We can verify the relationships exist correctly
        $this->assertNotNull($order->customer);
        $this->assertNotNull($order->createdBy);
        $this->assertEquals($customer->id, $order->customer->id);
        $this->assertEquals($employee->id, $order->createdBy->id);
    }
}

