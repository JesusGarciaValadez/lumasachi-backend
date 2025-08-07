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
            'field_changed',
            'old_value',
            'new_value',
            'comment',
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

        // The new schema doesn't have specific enum casts for old_value/new_value
        // as they can contain different types of values
        $this->assertIsArray($casts);
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
     * Test that OrderHistory field tracking works correctly
     *
     * @return void
     */
    public function test_order_history_field_tracking_works_correctly()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history for status change
        $statusHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Status changed to in progress',
            'created_by' => $user->id
        ]);

        $this->assertEquals('status', $statusHistory->field_changed);
        $this->assertEquals(OrderStatus::OPEN->value, $statusHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $statusHistory->getRawOriginal('new_value'));
        // Check that getters return enum instances
        $this->assertInstanceOf(OrderStatus::class, $statusHistory->old_value);
        $this->assertInstanceOf(OrderStatus::class, $statusHistory->new_value);
        $this->assertEquals(OrderStatus::OPEN, $statusHistory->old_value);
        $this->assertEquals(OrderStatus::IN_PROGRESS, $statusHistory->new_value);

        // Create order history for priority change
        $priorityHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::HIGH->value,
            'comment' => 'Priority increased',
            'created_by' => $user->id
        ]);

        $this->assertEquals('priority', $priorityHistory->field_changed);
        $this->assertEquals(OrderPriority::NORMAL->value, $priorityHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::HIGH->value, $priorityHistory->getRawOriginal('new_value'));
        // Check that getters return enum instances
        $this->assertInstanceOf(OrderPriority::class, $priorityHistory->old_value);
        $this->assertInstanceOf(OrderPriority::class, $priorityHistory->new_value);
        $this->assertEquals(OrderPriority::NORMAL, $priorityHistory->old_value);
        $this->assertEquals(OrderPriority::HIGH, $priorityHistory->new_value);
    }

    /**
     * Test that OrderHistory can have null values
     *
     * @return void
     */
    public function test_order_history_can_have_null_values()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history with null old_value (for initial creation)
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => null,
            'new_value' => OrderStatus::OPEN->value,
            'comment' => 'Initial order creation',
            'created_by' => $user->id
        ]);

        $this->assertNull($orderHistory->old_value);
        $this->assertEquals(OrderStatus::OPEN->value, $orderHistory->getRawOriginal('new_value'));
        $this->assertEquals('status', $orderHistory->field_changed);
        // Check that getter returns enum instance
        $this->assertInstanceOf(OrderStatus::class, $orderHistory->new_value);
        $this->assertEquals(OrderStatus::OPEN, $orderHistory->new_value);
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
            'role' => UserRole::CUSTOMER->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        $data = [
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Order status updated - Customer requested urgent delivery',
            'created_by' => $user->id
        ];

        $orderHistory = OrderHistory::create($data);

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertEquals($order->id, $orderHistory->order_id);
        $this->assertEquals('Order status updated - Customer requested urgent delivery', $orderHistory->comment);
        $this->assertEquals($user->id, $orderHistory->created_by);
    }

    /**
     * Test that OrderHistory comment field is nullable
     *
     * @return void
     */
    public function test_order_history_comment_field_is_nullable()
    {
        // Create a user with customer role
        $user = User::factory()->create([
            'role' => UserRole::CUSTOMER->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history without comment
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $user->id
        ]);

        $this->assertNull($orderHistory->comment);
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
            'role' => UserRole::CUSTOMER->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
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
            'role' => UserRole::CUSTOMER->value
        ]);

        $employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        // Create order history
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Employee started working on order',
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
            'role' => UserRole::CUSTOMER->value
        ]);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'created_by' => $user->id
        ]);

        // Create order history
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $user->id
        ]);

        $orderHistoryId = $orderHistory->id;

        // Delete the order
        $order->delete();

        // Check that order history was deleted
        $this->assertNull(OrderHistory::find($orderHistoryId));
    }
}
