<?php

namespace Modules\Lumasachi\tests\Unit\database\migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use App\Models\User;

final class CreateOrderHistoriesTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the order_histories table is created with all expected columns
     */
    public function test_order_histories_table_is_created_with_all_columns()
    {
        // Check if the table exists
        $this->assertTrue(Schema::hasTable('order_histories'));

        // Check all columns exist
        $columns = [
            'id',
            'order_id',
            'status_from',
            'status_to',
            'priority_from',
            'priority_to',
            'description',
            'notes',
            'created_by',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('order_histories', $column),
                "Column '{$column}' does not exist in order_histories table"
            );
        }
    }

    /**
     * Test nullable and required columns
     */
    public function test_nullable_and_required_columns()
    {
        // Create necessary related records
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Test required columns (should fail without them)
        $this->expectException(\Illuminate\Database\QueryException::class);
        OrderHistory::create([
            'order_id' => $order->id,
            'created_by' => $user->id,
            // Missing required 'description'
        ]);
    }

    /**
     * Test that nullable columns can be null
     */
    public function test_nullable_columns_can_be_null()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Test with nullable columns as null
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'description' => 'Test description',
            'created_by' => $user->id,
            // All nullable fields left as null
        ]);

        $this->assertNotNull($history);
        $this->assertNull($history->status_from);
        $this->assertNull($history->status_to);
        $this->assertNull($history->priority_from);
        $this->assertNull($history->priority_to);
        $this->assertNull($history->notes);
    }

    /**
     * Test foreign key constraints
     */
    public function test_foreign_key_constraints()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Create order history
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => 'Open',
            'status_to' => 'In Progress',
            'description' => 'Status changed',
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($history);

        // Test cascade delete on order
        $order->delete();
        $this->assertDatabaseMissing('order_histories', ['id' => $history->id]);
    }

    /**
     * Test creating order history with all fields
     */
    public function test_can_create_order_history_with_all_fields()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => 'Open',
            'status_to' => 'In Progress',
            'priority_from' => 'Normal',
            'priority_to' => 'High',
            'description' => 'Status and priority changed',
            'notes' => 'Customer requested urgent handling',
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(OrderHistory::class, $history);
        $this->assertEquals($order->id, $history->order_id);

        // For enum comparisons, check the value property
        $this->assertEquals('Open', $history->status_from->value);
        $this->assertEquals('In Progress', $history->status_to->value);
        $this->assertEquals('Normal', $history->priority_from->value);
        $this->assertEquals('High', $history->priority_to->value);

        $this->assertEquals('Status and priority changed', $history->description);
        $this->assertEquals('Customer requested urgent handling', $history->notes);
        $this->assertEquals($user->id, $history->created_by);
    }

    /**
     * Test UUID primary key
     */
    public function test_uuid_primary_key()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'description' => 'Test',
            'created_by' => $user->id,
        ]);

        // Check that ID is a valid UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $history->id
        );
    }

    /**
     * Test the migration down method
     */
    public function test_migration_rollback()
    {
        // First ensure the table exists
        $this->assertTrue(Schema::hasTable('order_histories'));

        // Run the specific migration down
        $this->artisan('migrate:rollback', [
            '--path' => 'modules/Lumasachi/database/migrations/2025_07_27_165842_create_order_histories_table.php'
        ]);

        // Check the table no longer exists
        $this->assertFalse(Schema::hasTable('order_histories'));
    }
}

