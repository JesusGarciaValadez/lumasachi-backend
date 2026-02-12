<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderHistoryControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    /**
     * Test listing order histories.
     */
    #[Test]
    public function it_checks_if_index_lists_order_histories(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        OrderHistory::factory()->count(3)->create();

        $first = $this->getJson('/api/v1/history');

        $first->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS');

        $v = (int) Cache::get('order_histories:version', 1);
        $filters = [
            'order_id' => null,
            'from_date' => null,
            'to_date' => null,
            'page' => 1,
            'per_page' => 15,
        ];
        ksort($filters);
        $signature = md5(json_encode($filters));
        $this->assertTrue(Cache::has("order_histories:index:v{$v}:f:{$signature}"));

        $second = $this->getJson('/api/v1/history');
        $second->assertStatus(200)
            ->assertHeader('X-Cache', 'HIT');

        $content = $second->json();
        $this->assertIsArray($content);
        if (isset($content['data'])) {
            $this->assertCount(3, $content['data']);
        } else {
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
            'assigned_to' => $user->id,
        ]);
        $orderHistoryData = [
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => null,
            'new_value' => OrderStatus::Delivered->value,
            'comment' => $this->faker->sentence(),
        ];

        $v1 = (int) Cache::get('order_histories:version', 0);

        $response = $this->postJson('/api/v1/history', $orderHistoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'order_id', 'field_changed', 'old_value', 'new_value', 'comment', 'description', 'created_by', 'created_at'],
            ]);

        $v2 = (int) Cache::get('order_histories:version', 0);
        $this->assertSame($v1 + 1, $v2, 'Order histories cache version should bump on create');

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

        $first = $this->getJson('/api/v1/history/'.$orderHistory->uuid);

        $first->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonStructure([
                'data' => ['id', 'order_id', 'field_changed', 'old_value', 'new_value', 'comment', 'description', 'created_by', 'created_at'],
            ]);

        $second = $this->getJson('/api/v1/history/'.$orderHistory->uuid);
        $second->assertStatus(200)->assertHeader('X-Cache', 'HIT');
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

        $v1 = (int) Cache::get('order_histories:version', 0);

        $response = $this->deleteJson('/api/v1/history/'.$orderHistory->uuid);

        $response->assertStatus(204);

        $v2 = (int) Cache::get('order_histories:version', 0);
        $this->assertSame($v1 + 1, $v2, 'Order histories cache version should bump on delete');

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

        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/history/'.$orderHistory->uuid.'/order/'.$orderHistory->order->uuid);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order' => ['id', 'status'],
            ]);
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

        $response = $this->getJson('/api/v1/history/'.$orderHistory->uuid.'/order/'.$orderHistory->order->uuid.'/attachments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'attachments' => [
                    '*' => ['id', 'file_name', 'url'],
                ],
            ]);
    }
}
