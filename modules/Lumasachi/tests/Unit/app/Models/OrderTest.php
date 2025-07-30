<?php

namespace Modules\Lumasachi\Tests\Unit\app\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Carbon\Carbon;

final class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Order model uses required traits
     */
    public function test_order_uses_required_traits(): void
    {
        $order = new Order();

        // Check for HasFactory trait
        $this->assertTrue(method_exists($order, 'factory'));

        // Check for HasUuids trait
        $this->assertTrue(method_exists($order, 'uniqueIds'));

        // Check for HasAttachments trait
        $this->assertTrue(method_exists($order, 'attachments'));
    }

    /**
     * Test that fillable attributes are set correctly
     */
    public function test_fillable_attributes(): void
    {
        $order = new Order();
        $fillable = $order->getFillable();

        $expectedFillable = [
            'customer_id',
            'title',
            'description',
            'status',
            'priority',
            'category',
            'estimated_completion',
            'actual_completion',
            'notes',
            'created_by',
            'updated_by',
            'assigned_to'
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    /**
     * Test that casts are set correctly
     */
    public function test_casts_attributes(): void
    {
        $order = new Order();
        $casts = $order->getCasts();

        $this->assertArrayHasKey('estimated_completion', $casts);
        $this->assertArrayHasKey('actual_completion', $casts);
        $this->assertStringContainsString('datetime', $casts['estimated_completion']);
        $this->assertStringContainsString('datetime', $casts['actual_completion']);
    }

    /**
     * Test status constants
     */
    public function test_status_constants(): void
    {
        $this->assertEquals('Open', Order::STATUS_OPEN);
        $this->assertEquals('In Progress', Order::STATUS_IN_PROGRESS);
        $this->assertEquals('Ready for delivery', Order::STATUS_READY_FOR_DELIVERY);
        $this->assertEquals('Delivered', Order::STATUS_DELIVERED);
        $this->assertEquals('Paid', Order::STATUS_PAID);
        $this->assertEquals('Returned', Order::STATUS_RETURNED);
        $this->assertEquals('Not paid', Order::STATUS_NOT_PAID);
        $this->assertEquals('Cancelled', Order::STATUS_CANCELLED);
    }

    /**
     * Test priority constants
     */
    public function test_priority_constants(): void
    {
        $this->assertEquals('Low', Order::PRIORITY_LOW);
        $this->assertEquals('Normal', Order::PRIORITY_NORMAL);
        $this->assertEquals('High', Order::PRIORITY_HIGH);
        $this->assertEquals('Urgent', Order::PRIORITY_URGENT);
    }

    /**
     * Test customer relationship
     */
    public function test_customer_relationship(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(User::class, $order->customer);
        $this->assertEquals($customer->id, $order->customer->id);
        $this->assertEquals(UserRole::CUSTOMER->value, $order->customer->role->value);
    }

    /**
     * Test customer relationship returns null for non-customer users
     */
    public function test_customer_relationship_returns_null_for_non_customers(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->create(['customer_id' => $employee->id]);

        $this->assertNull($order->customer);
    }

    /**
     * Test createdBy relationship
     */
    public function test_created_by_relationship(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $order->createdBy);
        $this->assertEquals($user->id, $order->createdBy->id);
    }

    /**
     * Test updatedBy relationship
     */
    public function test_updated_by_relationship(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['updated_by' => $user->id]);

        $this->assertInstanceOf(User::class, $order->updatedBy);
        $this->assertEquals($user->id, $order->updatedBy->id);
    }

    /**
     * Test assignedTo relationship
     */
    public function test_assigned_to_relationship(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->create(['assigned_to' => $employee->id]);

        $this->assertInstanceOf(User::class, $order->assignedTo);
        $this->assertEquals($employee->id, $order->assignedTo->id);
        $this->assertEquals(UserRole::EMPLOYEE->value, $order->assignedTo->role->value);
    }

    /**
     * Test assignedTo relationship returns null for non-employee users
     */
    public function test_assigned_to_relationship_returns_null_for_non_employees(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create(['assigned_to' => $customer->id]);

        $this->assertNull($order->assignedTo);
    }

    /**
     * Test orderHistories relationship
     */
    public function test_order_histories_relationship(): void
    {
        $order = Order::factory()->create();

        // Create order histories
        OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => Order::STATUS_OPEN,
            'status_to' => Order::STATUS_IN_PROGRESS,
            'description' => 'Order started',
            'created_by' => User::factory()->create()->id
        ]);

        OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => Order::STATUS_IN_PROGRESS,
            'status_to' => Order::STATUS_READY_FOR_DELIVERY,
            'description' => 'Order ready',
            'created_by' => User::factory()->create()->id
        ]);

        $this->assertCount(2, $order->orderHistories);
        $this->assertContainsOnlyInstancesOf(OrderHistory::class, $order->orderHistories);
    }

    /**
     * Test date casting for estimated_completion
     */
    public function test_estimated_completion_date_casting(): void
    {
        $date = now()->addDays(5);
        $order = Order::factory()->create([
            'estimated_completion' => $date
        ]);

        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $order->estimated_completion);
        $this->assertEquals($date->format('Y-m-d H:i:s'), $order->estimated_completion->format('Y-m-d H:i:s'));
    }

    /**
     * Test date casting for actual_completion
     */
    public function test_actual_completion_date_casting(): void
    {
        $date = now()->subDays(2);
        $order = Order::factory()->create([
            'actual_completion' => $date
        ]);

        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $order->actual_completion);
        $this->assertEquals($date->format('Y-m-d H:i:s'), $order->actual_completion->format('Y-m-d H:i:s'));
    }

    /**
     * Test actual_completion can be null
     */
    public function test_actual_completion_can_be_null(): void
    {
        $order = Order::factory()->create([
            'actual_completion' => null
        ]);

        $this->assertNull($order->actual_completion);
    }

    /**
     * Test mass assignment
     */
    public function test_mass_assignment(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $creator = User::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'title' => 'Test Order',
            'description' => 'Test Description',
            'status' => Order::STATUS_OPEN,
            'priority' => Order::PRIORITY_HIGH,
            'category' => 'Test Category',
            'estimated_completion' => now()->addDays(7),
            'notes' => 'Test notes',
            'created_by' => $creator->id,
            'updated_by' => $creator->id
        ];

        $order = Order::create($data);

        $this->assertEquals($data['title'], $order->title);
        $this->assertEquals($data['description'], $order->description);
        $this->assertEquals($data['status'], $order->status);
        $this->assertEquals($data['priority'], $order->priority);
        $this->assertEquals($data['category'], $order->category);
        $this->assertEquals($data['notes'], $order->notes);
    }

    /**
     * Test order can be created with minimum required fields
     */
    public function test_order_can_be_created_with_minimum_fields(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $creator = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'title' => 'Minimal Order',
            'description' => 'Minimal Description',
            'status' => Order::STATUS_OPEN,
            'priority' => Order::PRIORITY_NORMAL,
            'category' => 'General',
            'estimated_completion' => now()->addDays(3),
            'created_by' => $creator->id,
            'updated_by' => $creator->id
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertNotNull($order->id);
        $this->assertNull($order->actual_completion);
        $this->assertNull($order->notes);
        $this->assertNull($order->assigned_to);
    }

    /**
     * Test that newFactory returns correct factory instance
     */
    public function test_new_factory_returns_correct_instance(): void
    {
        $factory = Order::factory();

        $this->assertInstanceOf(\Modules\Lumasachi\database\factories\OrderFactory::class, $factory);
    }

    /**
     * Test UUID generation
     */
    public function test_uuid_generation(): void
    {
        $order = Order::factory()->create();

        $this->assertNotNull($order->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $order->id
        );
    }

    /**
     * Test model table name
     */
    public function test_model_table_name(): void
    {
        $order = new Order();

        $this->assertEquals('orders', $order->getTable());
    }

    /**
     * Test all status values are unique
     */
    public function test_all_status_values_are_unique(): void
    {
        $reflection = new \ReflectionClass(Order::class);
        $constants = $reflection->getConstants();

        $statusConstants = array_filter($constants, function($key) {
            return str_starts_with($key, 'STATUS_');
        }, ARRAY_FILTER_USE_KEY);

        $statusValues = array_values($statusConstants);
        $uniqueValues = array_unique($statusValues);

        $this->assertCount(count($statusValues), $uniqueValues, 'Status values should be unique');
    }

    /**
     * Test all priority values are unique
     */
    public function test_all_priority_values_are_unique(): void
    {
        $reflection = new \ReflectionClass(Order::class);
        $constants = $reflection->getConstants();

        $priorityConstants = array_filter($constants, function($key) {
            return str_starts_with($key, 'PRIORITY_');
        }, ARRAY_FILTER_USE_KEY);

        $priorityValues = array_values($priorityConstants);
        $uniqueValues = array_unique($priorityValues);

        $this->assertCount(count($priorityValues), $uniqueValues, 'Priority values should be unique');
    }
}
