<?php

namespace Tests\Feature\app\Http\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class OrderHistoryTrackingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_if_tracks_status_changes_when_updating_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $categories = Category::factory()->count(2)->create();
        $order = Order::factory()->createQuietly([
            'status' => OrderStatus::OPEN->value,
            // 'category_id' => $categories[0]->id,
            'created_by' => $user->id,
        ]);
        $order->categories()->attach($categories->pluck('id'));

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'status' => OrderStatus::IN_PROGRESS->value,
        ]);

        $response->assertOk();

        // Check that history was created
        $history = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_STATUS)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(OrderStatus::OPEN->value, $history->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $history->getRawOriginal('new_value'));
        $this->assertEquals($user->id, $history->created_by);
    }

    #[Test]
    public function it_checks_if_tracks_priority_changes_when_updating_order(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $categories = Category::factory()->count(2)->create();
        $order = Order::factory()->createQuietly([
            'priority' => OrderPriority::NORMAL->value,
            // 'category_id' => $categories[0]->id,
            'created_by' => $user->id,
        ]);
        $order->categories()->attach($categories->pluck('id'));

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'priority' => OrderPriority::URGENT->value,
        ]);

        $response->assertOk();

        // Check that history was created
        $history = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_PRIORITY)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(OrderPriority::NORMAL->value, $history->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::URGENT->value, $history->getRawOriginal('new_value'));
        $this->assertEquals($user->id, $history->created_by);
    }

    #[Test]
    public function it_checks_if_tracks_multiple_field_changes_in_single_update(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $categories = Category::factory()->count(2)->create();
        $newCategory = Category::factory()->create();
        $order = Order::factory()->createQuietly([
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::LOW->value,
            'title' => 'Original Title',
            // 'category_id' => $categories[0]->id,
        ]);
        $order->categories()->attach($categories->pluck('id'));

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'status' => OrderStatus::DELIVERED->value,
            'priority' => OrderPriority::HIGH->value,
            'title' => 'Updated Title',
            'categories' => [$newCategory->id],
        ]);

        $response->assertOk();

        // Check that multiple history entries were created
        $histories = OrderHistory::where('order_id', $order->id)->get();

        // Should have 4 history entries (status, priority, title, categories)
        $this->assertCount(4, $histories);

        // Verify each field change
        $statusHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_STATUS);
        $this->assertNotNull($statusHistory);
        $this->assertEquals(OrderStatus::OPEN->value, $statusHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::DELIVERED->value, $statusHistory->getRawOriginal('new_value'));

        $priorityHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_PRIORITY);
        $this->assertNotNull($priorityHistory);
        $this->assertEquals(OrderPriority::LOW->value, $priorityHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::HIGH->value, $priorityHistory->getRawOriginal('new_value'));

        $titleHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_TITLE);
        $this->assertNotNull($titleHistory);
        $this->assertEquals('Original Title', $titleHistory->old_value);
        $this->assertEquals('Updated Title', $titleHistory->new_value);

        $categoryHistory = $histories->firstWhere('field_changed', OrderHistory::FIELD_CATEGORIES);
        $this->assertNotNull($categoryHistory);
        $this->assertEquals(json_encode($categories->pluck('id')->toArray()), $categoryHistory->getRawOriginal('old_value'));
        $this->assertEquals(json_encode([$newCategory->id]), $categoryHistory->getRawOriginal('new_value'));
    }

    #[Test]
    public function it_checks_if_tracks_assignment_changes(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $employee = User::factory()->create();
        Sanctum::actingAs($user);

        $category = Category::factory()->create();
        $order = Order::factory()->createQuietly([
            'assigned_to' => $user->id,
            // 'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        $order->categories()->attach($category->id);

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'assigned_to' => $employee->id,
        ]);

        $response->assertOk();

        // Check that history was created
        $history = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_ASSIGNED_TO)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals($user->id, $history->old_value);
        $this->assertEquals($employee->id, $history->new_value);
    }

    #[Test]
    public function it_checks_if_tracks_estimated_completion_date_changes(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();
        $oldDate = Carbon::now()->addDays(5);
        $newDate = Carbon::now()->addDays(10);

        $order = Order::factory()->createQuietly([
            'estimated_completion' => $oldDate,
            // 'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        $order->categories()->attach($category->id);

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'estimated_completion' => $newDate->toISOString(),
        ]);

        $response->assertOk();

        // Check that history was created
        $history = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_ESTIMATED_COMPLETION)
            ->first();

        $this->assertNotNull($history);

        // Compare dates (ignoring microseconds)
        $oldHistoryDate = Carbon::parse($history->getRawOriginal('old_value'));
        $newHistoryDate = Carbon::parse($history->getRawOriginal('new_value'));

        $this->assertEquals($oldDate->format('Y-m-d H:i:s'), $oldHistoryDate->format('Y-m-d H:i:s'));
        $this->assertEquals($newDate->format('Y-m-d H:i:s'), $newHistoryDate->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_checks_if_does_not_create_history_when_no_changes_made(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();
        $order = Order::factory()->createQuietly([
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'title' => 'Test Order',
            // 'category_id' => $category->id,
        ]);
        $order->categories()->attach($category->id);

        // Count existing histories
        $initialHistoryCount = OrderHistory::where('order_id', $order->id)->count();

        // Update with same values
        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'title' => 'Test Order',
        ]);

        $response->assertOk();

        // Verify no new history entries were created
        $newHistoryCount = OrderHistory::where('order_id', $order->id)->count();
        $this->assertEquals($initialHistoryCount, $newHistoryCount);
    }

    #[Test]
    public function it_checks_if_tracks_setting_field_to_null(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();
        $order = Order::factory()->createQuietly([
            'notes' => 'Some important notes',
            // 'category_id' => $category->id,
        ]);
        $order->categories()->attach($category->id);

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'notes' => null,
        ]);

        $response->assertOk();

        // Check notes removal history
        $notesHistory = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', OrderHistory::FIELD_NOTES)
            ->first();

        $this->assertNotNull($notesHistory);
        $this->assertEquals('Some important notes', $notesHistory->old_value);
        $this->assertNull($notesHistory->new_value);
    }

    #[Test]
    public function it_checks_if_assigned_to_field_cannot_be_set_to_null(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $order = Order::factory()->createQuietly([
            'assigned_to' => User::factory()->create()->id,
        ]);

        $response = $this->putJson("/api/v1/orders/{$order->uuid}", [
            'assigned_to' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    }

    #[Test]
    public function it_checks_if_order_history_index_returns_paginated_results(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $order = Order::factory()->createQuietly();

        // Create multiple history entries
        OrderHistory::factory()->count(25)->create([
            'order_id' => $order->id,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'field_changed',
                        'old_value',
                        'new_value',
                        'comment',
                        'description',
                        'created_by',
                        'created_at',
                        'creator' => [
                            'id',
                            'full_name',
                            'email',
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        // Check pagination
        $this->assertCount(15, $response->json('data')); // Default pagination
        $this->assertEquals(25, $response->json('meta.total'));
    }

    #[Test]
    public function it_checks_if_order_history_index_filters_by_field(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $order = Order::factory()->createQuietly();

        // Create different types of history
        OrderHistory::factory()->count(5)->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
        ]);

        OrderHistory::factory()->count(3)->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_PRIORITY,
        ]);

        // Filter by status field
        $response = $this->getJson("/api/v1/orders/{$order->uuid}/history?field=" . OrderHistory::FIELD_STATUS);

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));

        // Verify all results are status changes
        foreach ($response->json('data') as $history) {
            $this->assertEquals(OrderHistory::FIELD_STATUS, $history['field_changed']);
        }
    }

    #[Test]
    public function it_checks_if_order_history_shows_human_readable_descriptions(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();
        $order = Order::factory()->createQuietly([
            'status' => OrderStatus::OPEN->value,
            // 'category_id' => $category->id,
        ]);
        $order->categories()->attach($category->id);

        // Create a status change
        $this->putJson("/api/v1/orders/{$order->uuid}", [
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");

        $response->assertOk();

        $history = $response->json('data.0');
        $this->assertNotNull($history['description']);
        $this->assertStringContainsString('Status changed from', $history['description']);
        $this->assertStringContainsString('Open', $history['description']);
        $this->assertStringContainsString('Delivered', $history['description']);
    }
}
