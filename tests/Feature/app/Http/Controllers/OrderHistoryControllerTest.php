<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use App\Models\User;
use App\Models\OrderHistory;
use App\Models\Order;
use App\Models\Attachment;
use App\Models\Category;
use PHPUnit\Framework\Attributes\Test;

class OrderHistoryControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test listing order histories.
     */
    #[Test]
    public function it_checks_if_index_lists_order_histories(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        OrderHistory::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/history');

        $response->assertStatus(200);

        // Debug the response
        $content = $response->json();
        // dd($content); // Uncomment to see actual response structure

        // Check if it has the basic pagination structure
        $this->assertIsArray($content);
        // Since it's a resource collection on a paginator, check for proper structure
        if (isset($content['data'])) {
            $this->assertCount(3, $content['data']);
        } else {
            // Direct array response
            $this->assertCount(3, $content);
        }
    }

    /**
     * Test creating an order history.
     */
    #[Test]
    public function it_checks_if_store_creates_new_order_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($user);

        $order = Order::factory()->createQuietly([
            'assigned_to' => $user->id
        ]);
        $order->categories()->attach(Category::factory()->create()->id);
        $orderHistoryData = [
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => null,
            'new_value' => OrderStatus::DELIVERED->value,
            'comment' => $this->faker->sentence(),
        ];

        $response = $this->postJson('/api/v1/history', $orderHistoryData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => ['id', 'order_id', 'field_changed', 'old_value', 'new_value', 'comment', 'description', 'created_by', 'created_at']
                ]);

        // Verify database has the correct data (without description since it's a calculated field)
        $this->assertDatabaseHas('order_histories', $orderHistoryData);

        // Verify the description accessor works correctly in the response
        $responseData = $response->json('data');
        $this->assertEquals('Status set to: Delivered', $responseData['description']);
    }

    /**
     * Test showing a specific order history.
     */
    #[Test]
    public function it_checks_if_show_order_history(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => ['id', 'order_id', 'field_changed', 'old_value', 'new_value', 'comment', 'description', 'created_by', 'created_at']
                ]);
    }

    /**
     * Test deleting an order history. Only SUPER_ADMINISTRATOR should delete.
     */
    #[Test]
    public function it_checks_if_destroy_order_history(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $user = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
        $this->actingAs($user);

        $response = $this->deleteJson('/api/v1/history/' . $orderHistory->uuid);

        $response->assertStatus(204);

        $this->assertModelMissing($orderHistory);
    }

    /**
     * Test fetching order related to order history.
     */
    #[Test]
    public function it_checks_if_order_for_order_history(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
        $order = Order::factory()->createQuietly(['customer_id' => $customer->id]);
        $orderHistory = OrderHistory::factory()->create(['order_id' => $order->id]);
        $orderHistory->order->categories()->attach(Category::factory()->create()->id);

        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid . '/order/' . $orderHistory->order->uuid);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'order' => ['id', 'status', 'categories']
                ]);
        $this->assertIsArray($response->json('order.categories'));
        $this->assertGreaterThan(0, count($response->json('order.categories') ?? []));
    }

    /**
     * Test fetching attachments for order related to order history.
     */
    #[Test]
    public function it_checks_if_order_attachments_for_order_history(): void
    {
        $orderHistory = OrderHistory::factory()->create();
        $attachments = Attachment::factory()->count(2)->create(['attachable_id' => $orderHistory->order_id, 'attachable_type' => 'order']);

        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid . '/order/' . $orderHistory->order->uuid . '/attachments');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'attachments' => [
                        '*' => ['id', 'file_name', 'url']
                    ]
                ]);
    }
}

