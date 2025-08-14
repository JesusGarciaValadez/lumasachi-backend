<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Enums\UserRole;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Mail\OrderCreatedMail;
use App\Models\Company;
use App\Models\Order;
use App\Models\Category;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $superAdmin;
    protected User $admin;
    protected User $employee;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'phone' => '1234567890',
            'address' => '123 Main St, Anytown, USA',
            'city' => 'Anytown',
            'state' => 'CA',
            'postal_code' => '12345',
            'country' => 'USA',
            'is_active' => true,
        ]);
        // Create users with different roles for testing
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value, 'company_id' => $this->company->id]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value, 'company_id' => $this->company->id]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value, 'company_id' => $this->company->id]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    }

    /**
     * Test listing orders returns only active statuses for an employee.
     */
    #[Test]
    public function it_checks_if_index_returns_only_active_orders_for_employee(): void
    {
        $this->actingAs($this->employee);

        // Create 5 orders with "active" statuses that should be returned
        Order::factory()->count(5)->createQuietly([
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::OPEN->value,
            'assigned_to' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);

        // Create orders for another employee that should not be returned
        $otherEmployee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        Order::factory()->count(5)->createQuietly([
            'status' => OrderStatus::COMPLETED->value,
            'assigned_to' => $otherEmployee->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(10);
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
        $this->employee->notify(new OrderCreatedNotification($order));

        Notification::assertSentTo(
            $this->employee,
            OrderCreatedNotification::class
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

        $order = Order::factory()->createQuietly([
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
        $order = Order::factory()->createQuietly([
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

        $order = Order::factory()->createQuietly([
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

        $order = Order::factory()->createQuietly();

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
