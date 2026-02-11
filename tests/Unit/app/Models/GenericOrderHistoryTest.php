<?php

declare(strict_types=1);

namespace Tests\Unit\app\Models;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GenericOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_if_can_track_status_changes(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::Open->value,
            'new_value' => OrderStatus::InProgress->value,
            'comment' => 'Started working on the order',
            'created_by' => $user->id,
        ]);

        $this->assertEquals(OrderHistory::FIELD_STATUS, $history->field_changed);
        $this->assertEquals(OrderStatus::Open->value, $history->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::InProgress->value, $history->getRawOriginal('new_value'));

        // Test automatic casting
        $this->assertInstanceOf(OrderStatus::class, $history->old_value);
        $this->assertInstanceOf(OrderStatus::class, $history->new_value);
        $this->assertEquals(OrderStatus::Open, $history->old_value);
        $this->assertEquals(OrderStatus::InProgress, $history->new_value);
    }

    #[Test]
    public function it_checks_if_can_track_priority_changes(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_PRIORITY,
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::URGENT->value,
            'comment' => 'Client requested urgent delivery',
            'created_by' => $user->id,
        ]);

        $this->assertEquals(OrderHistory::FIELD_PRIORITY, $history->field_changed);

        // Test automatic casting
        $this->assertInstanceOf(OrderPriority::class, $history->old_value);
        $this->assertInstanceOf(OrderPriority::class, $history->new_value);
        $this->assertEquals(OrderPriority::NORMAL, $history->old_value);
        $this->assertEquals(OrderPriority::URGENT, $history->new_value);
    }

    #[Test]
    public function it_checks_if_can_track_assignment_changes(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();
        $employee1 = User::factory()->create();
        $employee2 = User::factory()->create();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
            'old_value' => (string) $employee1->id,
            'new_value' => (string) $employee2->id,
            'comment' => 'Reassigned due to workload',
            'created_by' => $user->id,
        ]);

        $this->assertEquals(OrderHistory::FIELD_ASSIGNED_TO, $history->field_changed);
        $this->assertEquals((string) $employee1->id, $history->old_value);
        $this->assertEquals((string) $employee2->id, $history->new_value);
    }

    #[Test]
    public function it_checks_if_can_track_date_changes(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();
        $oldDate = Carbon::now()->subDays(5);
        $newDate = Carbon::now()->addDays(2);

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_ESTIMATED_COMPLETION,
            'old_value' => $oldDate->toISOString(),
            'new_value' => $newDate->toISOString(),
            'comment' => 'Delivery delayed due to supplier issue',
            'created_by' => $user->id,
        ]);

        $this->assertEquals(OrderHistory::FIELD_ESTIMATED_COMPLETION, $history->field_changed);

        // Test automatic casting to Carbon
        $this->assertInstanceOf(Carbon::class, $history->old_value);
        $this->assertInstanceOf(Carbon::class, $history->new_value);
        $this->assertEquals($oldDate->format('Y-m-d'), $history->old_value->format('Y-m-d'));
        $this->assertEquals($newDate->format('Y-m-d'), $history->new_value->format('Y-m-d'));
    }

    #[Test]
    public function it_checks_if_can_track_text_field_changes(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_TITLE,
            'old_value' => 'Old Title',
            'new_value' => 'New Updated Title',
            'comment' => 'Title corrected per client request',
            'created_by' => $user->id,
        ]);

        $this->assertEquals(OrderHistory::FIELD_TITLE, $history->field_changed);
        $this->assertEquals('Old Title', $history->old_value);
        $this->assertEquals('New Updated Title', $history->new_value);
    }

    #[Test]
    public function it_checks_if_can_handle_null_values(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        // Test setting a value from null
        $history1 = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => null,
            'new_value' => 'Some new notes',
            'comment' => 'Added notes',
            'created_by' => $user->id,
        ]);

        $this->assertNull($history1->old_value);
        $this->assertEquals('Some new notes', $history1->new_value);

        // Test setting a value to null
        $history2 = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
            'old_value' => '123',
            'new_value' => null,
            'comment' => 'Unassigned from employee',
            'created_by' => $user->id,
        ]);

        $this->assertEquals('123', $history2->old_value);
        $this->assertNull($history2->new_value);
    }

    #[Test]
    public function it_checks_if_generates_human_readable_descriptions(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        // Test status change description
        $history1 = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::Open->value,
            'new_value' => OrderStatus::InProgress->value,
            'created_by' => $user->id,
        ]);

        $expectedDescription = 'Status changed from Open to In Progress';
        $this->assertEquals($expectedDescription, $history1->description);

        // Test new value only
        $history2 = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_PRIORITY,
            'old_value' => null,
            'new_value' => OrderPriority::HIGH->value,
            'created_by' => $user->id,
        ]);

        $expectedDescription = 'Priority set to: High';
        $this->assertEquals($expectedDescription, $history2->description);

        // Test value removed
        $history3 = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => 'Some notes',
            'new_value' => null,
            'created_by' => $user->id,
        ]);

        $expectedDescription = 'Notes removed (was: Some notes)';
        $this->assertEquals($expectedDescription, $history3->description);
    }

    #[Test]
    public function it_checks_if_formats_date_values_in_description(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();
        $date = Carbon::now()->addDays(5);

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_ESTIMATED_COMPLETION,
            'old_value' => null,
            'new_value' => $date->toISOString(),
            'created_by' => $user->id,
        ]);

        $expectedDescription = 'Estimated completion set to: '.$date->format('Y-m-d H:i');
        $this->assertEquals($expectedDescription, $history->description);
    }

    #[Test]
    public function it_checks_if_maintains_backward_compatibility_with_enum_serialization(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        $history = new OrderHistory();
        $history->order_id = $order->id;
        $history->field_changed = OrderHistory::FIELD_STATUS;
        $history->created_by = $user->id;

        // Test setting enum directly
        $history->old_value = OrderStatus::Open;
        $history->new_value = OrderStatus::Delivered;

        $history->save();

        // Verify stored as string values
        $this->assertEquals(OrderStatus::Open->value, $history->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::Delivered->value, $history->getRawOriginal('new_value'));

        // Verify retrieved as enums
        $freshHistory = OrderHistory::find($history->id);
        $this->assertInstanceOf(OrderStatus::class, $freshHistory->old_value);
        $this->assertInstanceOf(OrderStatus::class, $freshHistory->new_value);
    }

    #[Test]
    public function it_checks_if_can_query_history_by_field(): void
    {
        $order = Order::factory()->createQuietly();
        $user = User::factory()->create();

        // Create different types of history
        OrderHistory::factory()->count(3)->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
        ]);

        OrderHistory::factory()->count(2)->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_PRIORITY,
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
        ]);

        // Query by field
        $statusChanges = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_STATUS)
            ->get();

        $priorityChanges = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_PRIORITY)
            ->get();

        $this->assertCount(3, $statusChanges);
        $this->assertCount(2, $priorityChanges);
    }

    #[Test]
    public function it_checks_if_factory_creates_valid_history_entries(): void
    {
        $history = OrderHistory::factory()->create();

        $this->assertNotNull($history->order_id);
        $this->assertNotNull($history->field_changed);
        $this->assertNotNull($history->created_by);
        $this->assertContains($history->field_changed, [
            OrderHistory::FIELD_STATUS,
            OrderHistory::FIELD_PRIORITY,
            OrderHistory::FIELD_ASSIGNED_TO,
            OrderHistory::FIELD_TITLE,
            OrderHistory::FIELD_ESTIMATED_COMPLETION,
            OrderHistory::FIELD_ACTUAL_COMPLETION,
            OrderHistory::FIELD_NOTES,
            OrderHistory::FIELD_CATEGORIES,
        ]);
    }

    #[Test]
    public function it_checks_if_factory_state_methods_work_correctly(): void
    {
        $order = Order::factory()->createQuietly();

        // Test status change state
        $statusHistory = OrderHistory::factory()
            ->statusChange(OrderStatus::Open, OrderStatus::Delivered)
            ->create(['order_id' => $order->id]);

        $this->assertEquals(OrderHistory::FIELD_STATUS, $statusHistory->field_changed);
        $this->assertEquals(OrderStatus::Open->value, $statusHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::Delivered->value, $statusHistory->getRawOriginal('new_value'));

        // Test priority change state
        $priorityHistory = OrderHistory::factory()
            ->priorityChange(OrderPriority::LOW, OrderPriority::HIGH)
            ->create(['order_id' => $order->id]);

        $this->assertEquals(OrderHistory::FIELD_PRIORITY, $priorityHistory->field_changed);
        $this->assertEquals(OrderPriority::LOW->value, $priorityHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::HIGH->value, $priorityHistory->getRawOriginal('new_value'));

        // Test assignment change state
        $oldAssignee = User::factory()->createQuietly();
        $newAssignee = User::factory()->createQuietly();

        $assignmentHistory = OrderHistory::factory()
            ->assignmentChange($oldAssignee, $newAssignee)
            ->create(['order_id' => $order->id]);

        $this->assertEquals(OrderHistory::FIELD_ASSIGNED_TO, $assignmentHistory->field_changed);
        $this->assertEquals($oldAssignee->id, $assignmentHistory->getRawOriginal('old_value'));
        $this->assertEquals($newAssignee->id, $assignmentHistory->getRawOriginal('new_value'));
    }
}
