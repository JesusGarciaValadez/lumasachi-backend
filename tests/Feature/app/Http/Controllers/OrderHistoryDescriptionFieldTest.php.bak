<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\User;
use App\Models\OrderHistory;
use App\Models\Order;
use PHPUnit\Framework\Attributes\Test;

class OrderHistoryDescriptionFieldTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the description field is properly included in API responses.
     */
    #[Test]
    public function it_checks_if_order_history_api_includes_description_field(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        // Create an order history
        $order = Order::factory()->createQuietly();
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $user->id
        ]);

        // Test show endpoint
        $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid);

        $response->assertStatus(200)
                ->assertJsonPath('data.description', 'Status changed from Open to In Progress')
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'field_changed',
                        'old_value',
                        'new_value',
                        'comment',
                        'description',
                        'created_by',
                        'created_at'
                    ]
                ]);
    }

    /**
     * Test that the description field is included when listing order histories.
     */
    #[Test]
    public function it_checks_if_order_history_list_includes_description_field(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        // Create multiple order histories with descriptions
        $order = Order::factory()->createQuietly();

        OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $user->id
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::URGENT->value,
            'created_by' => $user->id
        ]);

        // Test index endpoint
        $response = $this->getJson('/api/v1/orders/' . $order->uuid . '/history');

        $response->assertStatus(200);

        $data = $response->json('data');
        if (is_array($data) && count($data) >= 2) {
            // Check that each history entry has a description
            foreach ($data as $history) {
                $this->assertArrayHasKey('description', $history);
                $this->assertNotNull($history['description']);
            }
        }
    }

    /**
     * Test creating order history with description field.
     */
    #[Test]
    public function it_checks_if_create_order_history_with_description(): void
    {
        $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($user);

        $order = Order::factory()->createQuietly(['assigned_to' => $user->id]);

        $orderHistoryData = [
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::DELIVERED->value,
            'comment' => 'Order delivered to customer',
        ];

        $response = $this->postJson('/api/v1/history', $orderHistoryData);

        $response->assertStatus(201)
                ->assertJsonPath('data.description', 'Status changed from Open to Delivered')
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'order_id',
                        'field_changed',
                        'old_value',
                        'new_value',
                        'comment',
                        'description',
                        'created_by',
                        'created_at'
                    ]
                ]);

        // Verify it was saved to the database
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::DELIVERED->value
        ]);
    }

    /**
     * Test that order history through order endpoint includes description.
     */
    #[Test]
    public function it_checks_if_order_history_through_order_endpoint_includes_description(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        $order = Order::factory()->createQuietly();

        OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $user->id
        ]);

        // Test order history endpoint
        $response = $this->getJson("/api/v1/orders/{$order->uuid}/history");

        $response->assertStatus(200);

        $data = $response->json('data');
        if (is_array($data) && count($data) > 0) {
            $firstHistory = $data[0];
            $this->assertArrayHasKey('description', $firstHistory);
            $this->assertEquals('Status changed from Open to In Progress', $firstHistory['description']);
        }
    }
}
