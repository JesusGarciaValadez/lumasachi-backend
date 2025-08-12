<?php

namespace Modules\Lumasachi\Tests\Unit\app\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\Category;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Enums\UserRole;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Order model uses required traits
     */
    #[Test]
    public function it_checks_if_order_uses_required_traits(): void
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
    #[Test]
    public function it_checks_if_fillable_attributes_are_set_correctly(): void
    {
        $order = new Order();
        $fillable = $order->getFillable();

        $expectedFillable = [
            'customer_id',
            'title',
            'description',
            'status',
            'priority',
        'category_id',
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
    #[Test]
    public function it_checks_if_casts_attributes_are_set_correctly(): void
    {
        $order = new Order();
        $casts = $order->getCasts();

        $this->assertArrayHasKey('estimated_completion', $casts);
        $this->assertArrayHasKey('actual_completion', $casts);
        $this->assertStringContainsString('datetime', $casts['estimated_completion']);
        $this->assertStringContainsString('datetime', $casts['actual_completion']);
    }

    /**
     * Test customer relationship
     */
    #[Test]
    public function it_checks_if_customer_relationship_is_correct(): void
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
    #[Test]
    public function it_checks_if_customer_relationship_returns_null_for_non_customers(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->create(['customer_id' => $employee->id]);

        $this->assertNull($order->customer);
    }

    /**
     * Test createdBy relationship
     */
    #[Test]
    public function it_checks_if_created_by_relationship_is_correct(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $order->createdBy);
        $this->assertEquals($user->id, $order->createdBy->id);
    }

    /**
     * Test updatedBy relationship
     */
    #[Test]
    public function it_checks_if_updated_by_relationship_is_correct(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['updated_by' => $user->id]);

        $this->assertInstanceOf(User::class, $order->updatedBy);
        $this->assertEquals($user->id, $order->updatedBy->id);
    }

    /**
     * Test assignedTo relationship
     */
    #[Test]
    public function it_checks_if_assigned_to_relationship_is_correct(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->create(['assigned_to' => $employee->id]);

        $this->assertInstanceOf(User::class, $order->assignedTo);
        $this->assertEquals($employee->id, $order->assignedTo->id);
        $this->assertEquals(UserRole::EMPLOYEE->value, $order->assignedTo->role->value);
    }

    /**
     * Test orderHistories relationship
     */
    #[Test]
    public function it_checks_if_order_histories_relationship_is_correct(): void
    {
        $order = Order::factory()->create();

        // Create order histories
        OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN,
            'new_value' => OrderStatus::IN_PROGRESS,
            'comment' => 'Order started',
            'created_by' => User::factory()->create()->id
        ]);

        OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::IN_PROGRESS,
            'new_value' => OrderStatus::READY_FOR_DELIVERY,
            'comment' => 'Order ready',
            'created_by' => User::factory()->create()->id
        ]);

        $this->assertCount(2, $order->orderHistories);
        $this->assertContainsOnlyInstancesOf(OrderHistory::class, $order->orderHistories);
    }

    /**
     * Test can create an order
     */
    #[Test]
    public function it_checks_if_can_create_an_order(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $order = Order::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'created_by' => $user->id,
            'category_id' => $category->id,
        ]);
    }

    /**
     * Test belongs to a category
     */
    #[Test]
    public function it_checks_if_belongs_to_a_category(): void
    {
        $category = Category::factory()->create();
        $order = Order::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(BelongsTo::class, $order->category());
        $this->assertEquals($category->id, $order->category->id);
    }

    /**
     * Test belongs to a creator
     */
    #[Test]
    public function it_checks_if_belongs_to_a_creator(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $order->createdBy());
        $this->assertEquals($user->id, $order->createdBy->id);
    }

    /**
     * Test date casting for estimated_completion
     */
    #[Test]
    public function it_checks_if_estimated_completion_date_casting_is_correct(): void
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
    #[Test]
    public function it_checks_if_actual_completion_date_casting_is_correct(): void
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
    #[Test]
    public function it_checks_if_actual_completion_can_be_null(): void
    {
        $order = Order::factory()->create([
            'actual_completion' => null
        ]);

        $this->assertNull($order->actual_completion);
    }

    /**
     * Test mass assignment
     */
    #[Test]
    public function it_checks_if_mass_assignment_is_correct(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $creator = User::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'title' => 'Test Order',
            'description' => 'Test Description',
            'status' => OrderStatus::OPEN,
            'priority' => OrderPriority::HIGH,
            'category_id' => \Modules\Lumasachi\app\Models\Category::factory()->create()->id,
            'estimated_completion' => now()->addDays(7),
            'notes' => 'Test notes',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'assigned_to' => $creator->id,
        ];

        $order = Order::create($data);

        $this->assertEquals($data['title'], $order->title);
        $this->assertEquals($data['description'], $order->description);
        $this->assertEquals($data['status']->value, $order->status->value);
        $this->assertEquals($data['priority']->value, $order->priority->value);
        $this->assertEquals($data['category_id'], $order->category_id);
        $this->assertEquals($data['notes'], $order->notes);
    }

    /**
     * Test order can be created with minimum required fields
     */
    #[Test]
    public function it_checks_if_order_can_be_created_with_minimum_fields(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $creator = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'title' => 'Minimal Order',
            'description' => 'Minimal Description',
            'status' => OrderStatus::OPEN,
            'priority' => OrderPriority::NORMAL,
            'category_id' => \Modules\Lumasachi\app\Models\Category::factory()->create()->id,
            'estimated_completion' => now()->addDays(3),
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'assigned_to' => $creator->id,
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertNotNull($order->id);
        $this->assertNull($order->actual_completion);
        $this->assertNull($order->notes);
        $this->assertEquals($creator->id, $order->assigned_to);
    }

    /**
     * Test that newFactory returns correct factory instance
     */
    #[Test]
    public function it_checks_if_new_factory_returns_correct_instance(): void
    {
        $factory = Order::factory();

        $this->assertInstanceOf(\Modules\Lumasachi\database\factories\OrderFactory::class, $factory);
    }

    /**
     * Test UUID generation
     */
    #[Test]
    public function it_checks_if_uuid_generation_is_correct(): void
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
    #[Test]
    public function it_checks_if_model_table_name_is_correct(): void
    {
        $order = new Order();

        $this->assertEquals('orders', $order->getTable());
    }

    /**
     * Test all status values are unique
     */
    #[Test]
    public function it_checks_if_all_status_values_are_unique(): void
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
    #[Test]
    public function it_checks_if_all_priority_values_are_unique(): void
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
