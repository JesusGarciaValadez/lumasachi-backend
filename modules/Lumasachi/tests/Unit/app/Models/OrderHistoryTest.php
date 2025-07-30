<?php

namespace Modules\Lumasachi\tests\Unit\app\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use App\Models\User;
use Modules\Lumasachi\database\factories\OrderHistoryFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Lumasachi\app\Enums\UserRole;

final class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that OrderHistory uses required traits
     *
     * @return void
     */
    public function test_order_history_uses_required_traits()
    {
        $uses = class_uses(OrderHistory::class);

        $this->assertArrayHasKey('Illuminate\Database\Eloquent\Factories\HasFactory', $uses);
        $this->assertArrayHasKey('Illuminate\Database\Eloquent\Concerns\HasUuids', $uses);
        $this->assertArrayHasKey('Modules\Lumasachi\app\Traits\HasAttachments', $uses);
    }

    /**
     * Test that OrderHistory has correct fillable attributes
     *
     * @return void
     */
    public function test_order_history_has_correct_fillable_attributes()
    {
        $orderHistory = new OrderHistory();

        $expected = [
            'order_id',
            'status_from',
            'status_to',
            'priority_from',
            'priority_to',
            'description',
            'notes',
            'created_by'
        ];

        $this->assertEquals($expected, $orderHistory->getFillable());
    }

    /**
     * Test that OrderHistory has correct casts
     *
     * @return void
     */
    public function test_order_history_has_correct_casts()
    {
        $orderHistory = new OrderHistory();
        $casts = $orderHistory->getCasts();

        $this->assertArrayHasKey('status_from', $casts);
        $this->assertEquals(OrderStatus::class, $casts['status_from']);

        $this->assertArrayHasKey('status_to', $casts);
        $this->assertEquals(OrderStatus::class, $casts['status_to']);

        $this->assertArrayHasKey('priority_from', $casts);
        $this->assertEquals(OrderPriority::class, $casts['priority_from']);

        $this->assertArrayHasKey('priority_to', $casts);
        $this->assertEquals(OrderPriority::class, $casts['priority_to']);
    }

    /**
     * Test that OrderHistory belongs to Order
     *
     * @return void
     */
    public function test_order_history_belongs_to_order()
    {
        $orderHistory = new OrderHistory();
        $relation = $orderHistory->order();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('order_id', $relation->getForeignKeyName());
        $this->assertEquals(Order::class, $relation->getRelated()::class);
    }

    /**
     * Test that OrderHistory belongs to User as createdBy
     *
     * @return void
     */
    public function test_order_history_belongs_to_user_as_created_by()
    {
        $orderHistory = new OrderHistory();
        $relation = $orderHistory->createdBy();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('created_by', $relation->getForeignKeyName());
        $this->assertEquals(User::class, $relation->getRelated()::class);
    }

    /**
     * Test that OrderHistory can be created with factory
     *
     * @return void
     */
    public function test_order_history_can_be_created_with_factory()
    {
        $factory = OrderHistory::factory();

        $this->assertInstanceOf(OrderHistoryFactory::class, $factory);
    }

    /**
     * Test that OrderHistory status casting works correctly
     *
     * @return void
     */
    public function test_order_history_status_casting_works_correctly()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::HIGH->value,
            'description' => 'Status changed to in progress',
            'created_by' => $user->id
        ]);

        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_from);
        $this->assertEquals(OrderStatus::OPEN, $orderHistory->status_from);

        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_to);
        $this->assertEquals(OrderStatus::IN_PROGRESS, $orderHistory->status_to);

        $this->assertInstanceOf(OrderPriority::class, $orderHistory->priority_from);
        $this->assertEquals(OrderPriority::NORMAL, $orderHistory->priority_from);

        $this->assertInstanceOf(OrderPriority::class, $orderHistory->priority_to);
        $this->assertEquals(OrderPriority::HIGH, $orderHistory->priority_to);
    }

    /**
     * Test that OrderHistory can have null status and priority values
     *
     * @return void
     */
    public function test_order_history_can_have_null_status_and_priority_values()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history with null values
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => null,
            'status_to' => OrderStatus::OPEN->value,
            'priority_from' => null,
            'priority_to' => null,
            'description' => 'Initial order creation',
            'created_by' => $user->id
        ]);

        $this->assertNull($orderHistory->status_from);
        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_to);
        $this->assertNull($orderHistory->priority_from);
        $this->assertNull($orderHistory->priority_to);
    }

    /**
     * Test that OrderHistory can be created through mass assignment
     *
     * @return void
     */
    public function test_order_history_can_be_created_through_mass_assignment()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        $data = [
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::HIGH->value,
            'description' => 'Order status updated',
            'notes' => 'Customer requested urgent delivery',
            'created_by' => $user->id
        ];

        $orderHistory = OrderHistory::create($data);

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertEquals($order->id, $orderHistory->order_id);
        $this->assertEquals('Order status updated', $orderHistory->description);
        $this->assertEquals('Customer requested urgent delivery', $orderHistory->notes);
        $this->assertEquals($user->id, $orderHistory->created_by);
    }

    /**
     * Test that OrderHistory notes field is nullable
     *
     * @return void
     */
    public function test_order_history_notes_field_is_nullable()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history without notes
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Status updated',
            'created_by' => $user->id
        ]);

        $this->assertNull($orderHistory->notes);
    }

    /**
     * Test that OrderHistory generates UUID
     *
     * @return void
     */
    public function test_order_history_generates_uuid()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Status updated',
            'created_by' => $user->id
        ]);

        $this->assertNotNull($orderHistory->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $orderHistory->id
        );
    }

    /**
     * Test that OrderHistory has correct table name
     *
     * @return void
     */
    public function test_order_history_has_correct_table_name()
    {
        $orderHistory = new OrderHistory();

        $this->assertEquals('order_histories', $orderHistory->getTable());
    }

    /**
     * Test that OrderHistory relationships load correctly
     *
     * @return void
     */
    public function test_order_history_relationships_load_correctly()
    {
        // Create users
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        $employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        // Create order history
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Employee started working on order',
            'created_by' => $employee->id
        ]);

        // Load relationships
        $orderHistory->load(['order', 'createdBy']);

        $this->assertEquals($order->id, $orderHistory->order->id);
        $this->assertEquals($employee->id, $orderHistory->createdBy->id);
    }

    /**
     * Test that OrderHistory cascades on order delete
     *
     * @return void
     */
    public function test_order_history_cascades_on_order_delete()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Status updated',
            'created_by' => $user->id
        ]);

        $orderHistoryId = $orderHistory->id;

        // Delete the order
        $order->delete();

        // Check that order history was deleted
        $this->assertNull(OrderHistory::find($orderHistoryId));
    }
}
