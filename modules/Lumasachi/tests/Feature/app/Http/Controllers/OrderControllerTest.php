<?php

namespace Modules\Lumasachi\tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\Category;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $employee;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with different roles for testing
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    /**
     * Test listing orders returns all orders without pagination
     */
    public function test_index_returns_all_orders_without_pagination()
    {
        $this->actingAs($this->employee);

        // Create multiple orders
        $orders = Order::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(25)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'category',
                    'estimated_completion',
                    'actual_completion',
                    'notes',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test creating an order with valid data
     */
    public function test_store_creates_order_with_valid_data()
    {
        $this->actingAs($this->employee);

        $orderData = [
            'customer_id' => $this->customer->id,
            'title' => 'Test Order Title',
            'description' => 'Test order description',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::HIGH->value,
            'category_id' => Category::factory()->create()->id,
            'notes' => 'Some notes about the order',
            'assigned_to' => $this->employee->id
        ];

        $response = $this->postJson('/api/v1/orders', $orderData);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Order created successfully.',
                'order' => [
                    'title' => 'Test Order Title',
                    'description' => 'Test order description',
                    'status' => OrderStatus::OPEN->value,
                    'priority' => OrderPriority::HIGH->value,
                    'category' => Category::find($orderData['category_id'])->name
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'title' => 'Test Order Title',
            'customer_id' => $this->customer->id,
            'created_by' => $this->employee->id,
            'updated_by' => $this->employee->id
        ]);
    }

    /**
     * Test validation errors when creating order with invalid data
     */
    public function test_store_validation_fails_with_invalid_data()
    {
        $this->actingAs($this->employee);

        $response = $this->postJson('/api/v1/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'title', 'description', 'category_id']);
    }

    /**
     * Test store with invalid status
     */
    public function test_store_fails_with_invalid_status()
    {
        $this->actingAs($this->employee);

        $orderData = [
            'customer_id' => $this->customer->id,
            'title' => 'Test Order',
            'description' => 'Test description',
            'status' => 'InvalidStatus',
            'priority' => OrderPriority::NORMAL->value,
            'category_id' => Category::factory()->create()->id
        ];

        $response = $this->postJson('/api/v1/orders', $orderData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test showing a specific order
     */
    public function test_show_returns_order_with_relationships()
    {
        $this->actingAs($this->employee);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id
        ]);

        $response = $this->getJson('/api/v1/orders/' . $order->id);

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'status',
                'priority',
                'category',
                'customer' => ['id', 'first_name', 'last_name', 'email'],
                'assigned_to' => ['id', 'first_name', 'last_name', 'email'],
                'created_by' => ['id', 'first_name', 'last_name', 'email'],
                'updated_by' => ['id', 'first_name', 'last_name', 'email']
            ]);
    }

    /**
     * Test updating an order with valid data
     */
    public function test_update_modifies_order_successfully()
    {
        $this->actingAs($this->employee);

        // Create an order that the employee created
        $order = Order::factory()->create([
            'created_by' => $this->employee->id,
            'assigned_to' => $this->employee->id
        ]);

        $updateData = [
            'title' => 'Updated Order Title',
            'status' => OrderStatus::IN_PROGRESS->value,
            'priority' => OrderPriority::URGENT->value
        ];

        $response = $this->putJson('/api/v1/orders/' . $order->id, $updateData);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order updated successfully.',
                'order' => [
                    'title' => 'Updated Order Title',
                    'status' => OrderStatus::IN_PROGRESS->value,
                    'priority' => OrderPriority::URGENT->value
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'title' => 'Updated Order Title',
            'updated_by' => $this->employee->id
        ]);
    }

    /**
     * Test partial update of an order
     */
    public function test_update_allows_partial_updates()
    {
        $this->actingAs($this->employee);

        $order = Order::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'created_by' => $this->employee->id,
            'assigned_to' => $this->employee->id
        ]);

        $response = $this->putJson('/api/v1/orders/' . $order->id, [
            'title' => 'New Title Only'
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'title' => 'New Title Only',
            'description' => 'Original Description' // Should remain unchanged
        ]);
    }

    /**
     * Test deleting an order
     */
    public function test_destroy_deletes_order_successfully()
    {
        $this->actingAs($this->superAdmin);

        $order = Order::factory()->create();

        $response = $this->deleteJson('/api/v1/orders/' . $order->id);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order deleted successfully.'
            ]);

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id
        ]);
    }

    /**
     * Test unauthorized access returns 401
     */
    public function test_unauthenticated_access_returns_401()
    {
        $response = $this->getJson('/api/v1/orders');
        $response->assertUnauthorized();
    }

    /**
     * Test order not found returns 404
     */
    public function test_show_non_existent_order_returns_404()
    {
        $this->actingAs($this->employee);

        $response = $this->getJson('/api/v1/orders/non-existent-id');
        $response->assertNotFound();
    }
}

