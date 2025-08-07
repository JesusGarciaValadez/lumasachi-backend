<?php

namespace Modules\Lumasachi\tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\Category;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Notifications\OrderCreatedNotification;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
     * Test listing orders returns only active statuses for an employee.
     */
    #[Test]
    public function it_checks_if_index_returns_only_active_orders_for_employee(): void
    {
        $this->actingAs($this->employee);

        // Create 5 orders with "active" statuses that should be returned
        Order::factory()->count(5)->create(['status' => OrderStatus::OPEN]);

        // Create 3 orders with "inactive" statuses that should NOT be returned
        Order::factory()->count(3)->create(['status' => OrderStatus::CANCELLED]);
        Order::factory()->count(2)->create(['status' => OrderStatus::DELIVERED]);


        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(5);
    }

    /**
     * Test creating an order with valid data and sends a notification.
     */
    #[Test]
    public function it_checks_if_store_creates_order_with_valid_data(): void
    {
        Notification::fake();

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

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'title' => 'Test Order Title',
            'created_by' => $this->employee->id,
        ]);

        $order = Order::firstWhere('title', 'Test Order Title');

        Notification::assertSentTo(
            $this->employee,
            function (OrderCreatedNotification $notification) use ($order) {
                // First, basic check that the correct order is in the notification
                if ($notification->order->id !== $order->id) {
                    return false;
                }

                // Now, check the mailable content
                $mailData = $notification->toMail($this->employee)->toArray();

                $this->assertEquals('New Order Created: #' . $order->id, $mailData['subject']);
                $this->assertStringContainsString('A new order has been created.', $mailData['introLines'][0]);
                $this->assertStringContainsString('Order ID: ' . $order->id, $mailData['introLines'][1]);
                $this->assertEquals('View Order', $mailData['actionText']);
                $this->assertEquals(url('/orders/' . $order->id), $mailData['actionUrl']);
                $this->assertStringContainsString('Thank you for your business!', $mailData['outroLines'][0]);

                return true;
            }
        );
    }

    /**
     * Test validation errors when creating order with invalid data
     */
    #[Test]
    public function it_checks_if_store_validation_fails_with_invalid_data(): void
    {
        $this->actingAs($this->employee);

        $response = $this->postJson('/api/v1/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'title', 'description', 'category_id']);
    }

    /**
     * Test store with invalid status
     */
    #[Test]
    public function it_checks_if_store_fails_with_invalid_status(): void
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
    #[Test]
    public function it_checks_if_show_returns_order_with_relationships(): void
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
    #[Test]
    public function it_checks_if_update_modifies_order_successfully(): void
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
    #[Test]
    public function it_checks_if_update_allows_partial_updates(): void
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
    #[Test]
    public function it_checks_if_destroy_deletes_order_successfully(): void
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
    #[Test]
    public function it_checks_if_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/v1/orders');
        $response->assertUnauthorized();
    }

    /**
     * Test order not found returns 404
     */
    #[Test]
    public function it_checks_if_show_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->employee);

        $response = $this->getJson('/api/v1/orders/non-existent-id');
        $response->assertNotFound();
    }
}
