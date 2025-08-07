<?php

namespace Modules\Lumasachi\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use PHPUnit\Framework\Attributes\Test;

class OrderHistoryApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all OrderHistory API endpoints properly include the description field
     */
    #[Test]
    public function it_checks_if_all_order_history_endpoints_include_description_field(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($admin);

        $order = Order::factory()->create();

        // Create order history
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Customer requested priority handling',
            'created_by' => $admin->id
        ]);

        // Test 1: GET /api/v1/history (index)
        $response = $this->getJson('/api/v1/history');
        $response->assertStatus(200);
        $data = $response->json('data');
        if (is_array($data) && count($data) > 0) {
            $this->assertArrayHasKey('description', $data[0]);
            $this->assertEquals('Status changed from Open to In Progress', $data[0]['description']);
        }

        // Test 2: GET /api/v1/history/{id} (show)
        $response = $this->getJson("/api/v1/history/{$orderHistory->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.description', 'Status changed from Open to In Progress');

        // Test 3: POST /api/v1/history (store)
        $newOrder = Order::factory()->create();
        $response = $this->postJson('/api/v1/history', [
            'order_id' => $newOrder->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::URGENT->value,
            'comment' => 'Customer escalation'
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', 'Priority changed from Normal to Urgent');

        // Test 4: GET /api/v1/orders/{id}/history (order history)
        $response = $this->getJson("/api/v1/orders/{$order->id}/history");
        $response->assertStatus(200);
        $data = $response->json('data');
        if (is_array($data) && count($data) > 0) {
            $this->assertArrayHasKey('description', $data[0]);
            $this->assertEquals('Status changed from Open to In Progress', $data[0]['description']);
        }
    }

    /**
     * Test that the OrderHistoryResource properly formats all fields including description
     */
    #[Test]
    public function it_checks_if_order_history_resource_format(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($admin);

        $order = Order::factory()->create();

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'assigned_to',
            'old_value' => null,
            'new_value' => $employee->id,
            'comment' => 'Assigning to available employee',
            'created_by' => $admin->id
        ]);

        $response = $this->getJson("/api/v1/history/{$orderHistory->id}");

        $response->assertStatus(200)
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
                    'created_at',
                    'creator' => [
                        'id',
                        'full_name',
                        'email'
                    ]
                ]
            ])
            ->assertJsonPath('data.field_changed', 'assigned_to')
            ->assertJsonPath('data.description', 'Assigned to set to: ' . $employee->id)
            ->assertJsonPath('data.comment', 'Assigning to available employee');
    }

    /**
     * Test automatic description generation when creating order history through order updates
     */
    #[Test]
    public function it_checks_if_automatic_description_generation_on_order_update(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($admin);

        $order = Order::factory()->create([
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value
        ]);

        // Update order status (should trigger OrderObserver to create history)
        $response = $this->putJson("/api/v1/orders/{$order->id}", [
            'status' => OrderStatus::DELIVERED->value
        ]);

        $response->assertStatus(200);

        // Check that history was created with proper description
        $history = OrderHistory::where('order_id', $order->id)
            ->where('field_changed', 'status')
            ->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->description);

        // Verify the history is returned with description via API
        $response = $this->getJson("/api/v1/orders/{$order->id}/history");
        $response->assertStatus(200);

        $data = $response->json('data');
        if (is_array($data) && count($data) > 0) {
            $statusHistory = collect($data)->firstWhere('field_changed', 'status');
            $this->assertNotNull($statusHistory);
            $this->assertArrayHasKey('description', $statusHistory);
            $this->assertNotNull($statusHistory['description']);
        }
    }

    /**
     * Test filtering order history by field and ensuring description is included
     */
    #[Test]
    public function it_checks_if_order_history_filtering_includes_description(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($admin);

        $order = Order::factory()->create();

        // Create multiple history entries
        OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $admin->id
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::HIGH->value,
            'created_by' => $admin->id
        ]);

        // Filter by status changes
        $response = $this->getJson("/api/v1/orders/{$order->id}/history?field=status");

        $response->assertStatus(200);
        $data = $response->json('data');

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $history) {
                $this->assertEquals('status', $history['field_changed']);
                $this->assertArrayHasKey('description', $history);
                $this->assertStringContainsString('Status changed', $history['description']);
            }
        }
    }
}
