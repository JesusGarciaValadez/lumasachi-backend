<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if order histories table is created with all columns', function () {
    // Check if the table exists
    expect(Schema::hasTable('order_histories'))->toBeTrue();

    // Check all columns exist
    $columns = [
        'id',
        'order_id',
        'field_changed',
        'event_type',
        'old_value',
        'new_value',
        'comment',
        'created_by',
        'created_at',
        'updated_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('order_histories', $column))->toBeTrue("Column '{$column}' does not exist in order_histories table");
    }
});
it('checks if nullable and required columns', function () {
    // Create necessary related records
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Test required columns (should fail without them)
    $this->expectException(Illuminate\Database\QueryException::class);
    OrderHistory::create([
        'order_id' => $order->id,
        'created_by' => $user->id,
        // Missing required 'field_changed'
    ]);
});
it('checks if nullable columns can be null', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Test with nullable columns as null
    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => OrderLifecycleStatus::Received,
        'new_value' => OrderLifecycleStatus::AwaitingReview,
        'created_by' => $user->id,
        // Comment is nullable and left as null
    ]);

    expect($history)->not->toBeNull();
    expect($history->comment)->toBeNull();
});
it('checks if foreign key constraints', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create order history
    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => OrderLifecycleStatus::Received,
        'new_value' => OrderLifecycleStatus::AwaitingReview,
        'comment' => 'Status changed',
        'created_by' => $user->id,
    ]);

    expect($history)->not->toBeNull();

    // Test cascade delete on order
    $order->delete();
    $this->assertDatabaseMissing('order_histories', ['id' => $history->id]);
});
it('checks if can create order history with all fields', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => OrderLifecycleStatus::Received->value,
        'new_value' => OrderLifecycleStatus::AwaitingReview->value,
        'comment' => 'Status changed - Customer requested urgent handling',
        'created_by' => $user->id,
    ]);

    expect($history)->toBeInstanceOf(OrderHistory::class);
    expect($history->order_id)->toEqual($order->id);
    expect($history->field_changed)->toEqual('lifecycle_status');
    expect($history->old_value->value)->toEqual('Received');
    expect($history->new_value->value)->toEqual('Awaiting Review');
    expect($history->comment)->toEqual('Status changed - Customer requested urgent handling');
    expect($history->created_by)->toEqual($user->id);
});
it('checks if uuid primary key', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => OrderLifecycleStatus::Received,
        'new_value' => OrderLifecycleStatus::AwaitingReview,
        'created_by' => $user->id,
    ]);

    // Check that ID is a valid UUID format
    expect($history->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
it('checks if migration rollback', function () {
    // First ensure the table exists
    expect(Schema::hasTable('order_histories'))->toBeTrue();

    // Run the specific migration down
    $this->artisan('migrate:rollback', [
        '--path' => 'database/migrations/2025_07_27_165842_create_order_histories_table.php',
    ]);

    // Check the table no longer exists
    expect(Schema::hasTable('order_histories'))->toBeFalse();
});
