<?php

namespace Tests\Unit\database\migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class CreateOrderHistoriesTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the order_histories table is created with all expected columns
     */
    #[Test]
    public function it_checks_if_order_histories_table_is_created_with_all_columns(): void
    {
        // Check if the table exists
        $this->assertTrue(Schema::hasTable('order_histories'));

        // Check all columns exist
        $columns = [
            'id',
            'order_id',
            'field_changed',
            'old_value',
            'new_value',
            'comment',
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
    #[Test]
    public function it_checks_if_nullable_and_required_columns(): void
    {
        // Create necessary related records
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Test required columns (should fail without them)
        $this->expectException(\Illuminate\Database\QueryException::class);
        OrderHistory::create([
            'order_id' => $order->id,
            'created_by' => $user->id,
            // Missing required 'field_changed'
        ]);
    }

    /**
     * Test that nullable columns can be null
     */
    #[Test]
    public function it_checks_if_nullable_columns_can_be_null(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Test with nullable columns as null
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN,
            'new_value' => OrderStatus::IN_PROGRESS,
            'created_by' => $user->id,
            // Comment is nullable and left as null
        ]);

        $this->assertNotNull($history);
        $this->assertNull($history->comment);
    }

    /**
     * Test foreign key constraints
     */
    #[Test]
    public function it_checks_if_foreign_key_constraints(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Create order history
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN,
            'new_value' => OrderStatus::IN_PROGRESS,
            'comment' => 'Status changed',
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
    #[Test]
    public function it_checks_if_can_create_order_history_with_all_fields(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Status changed - Customer requested urgent handling',
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(OrderHistory::class, $history);
        $this->assertEquals($order->id, $history->order_id);
        $this->assertEquals('status', $history->field_changed);
        $this->assertEquals('Open', $history->old_value->value);
        $this->assertEquals('In Progress', $history->new_value->value);
        $this->assertEquals('Status changed - Customer requested urgent handling', $history->comment);
        $this->assertEquals($user->id, $history->created_by);
    }

    /**
     * Test UUID primary key
     */
    #[Test]
    public function it_checks_if_uuid_primary_key(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN,
            'new_value' => OrderStatus::IN_PROGRESS,
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
    #[Test]
    public function it_checks_if_migration_rollback(): void
    {
        // First ensure the table exists
        $this->assertTrue(Schema::hasTable('order_histories'));

        // Run the specific migration down
        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2025_07_27_165842_create_order_histories_table.php'
        ]);

        // Check the table no longer exists
        $this->assertFalse(Schema::hasTable('order_histories'));
    }
}
