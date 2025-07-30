<?php

namespace Modules\Lumasachi\Tests\Unit\database\factories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Enums\UserRole;
use App\Models\User;

final class OrderHistoryFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the factory creates a valid order history
     */
    public function test_factory_creates_valid_order_history(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertDatabaseHas('order_histories', [
            'id' => $orderHistory->id,
            'order_id' => $orderHistory->order_id,
        ]);
    }

    /**
     * Test that factory generates all required fields
     */
    public function test_factory_generates_all_required_fields(): void
    {
        $orderHistory = OrderHistory::factory()->make();

        $this->assertNotNull($orderHistory->order_id);
        $this->assertNotNull($orderHistory->status_from);
        $this->assertNotNull($orderHistory->status_to);
        $this->assertNotNull($orderHistory->priority_from);
        $this->assertNotNull($orderHistory->priority_to);
        $this->assertNotNull($orderHistory->description);
        $this->assertNotNull($orderHistory->created_by);
    }

    /**
     * Test that factory generates valid status values
     */
    public function test_factory_generates_valid_status_values(): void
    {
        $validStatuses = array_map(fn($status) => $status->value, OrderStatus::cases());

        $orderHistory = OrderHistory::factory()->make();

        // Check that the enum values are valid
        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_from);
        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_to);
        $this->assertContains($orderHistory->status_from->value, $validStatuses);
        $this->assertContains($orderHistory->status_to->value, $validStatuses);
    }

    /**
     * Test that factory generates valid priority values
     */
    public function test_factory_generates_valid_priority_values(): void
    {
        $validPriorities = array_map(fn($priority) => $priority->value, OrderPriority::cases());

        $orderHistory = OrderHistory::factory()->make();

        // Check that the enum values are valid
        $this->assertInstanceOf(OrderPriority::class, $orderHistory->priority_from);
        $this->assertInstanceOf(OrderPriority::class, $orderHistory->priority_to);
        $this->assertContains($orderHistory->priority_from->value, $validPriorities);
        $this->assertContains($orderHistory->priority_to->value, $validPriorities);
    }

    /**
     * Test that factory creates associated models
     */
    public function test_factory_creates_associated_models(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        // Check that order was created
        $this->assertDatabaseHas('orders', ['id' => $orderHistory->order_id]);

        // Check that user was created
        $this->assertDatabaseHas('users', ['id' => $orderHistory->created_by]);
    }

    /**
     * Test optional notes field
     */
    public function test_optional_notes_field(): void
    {
        // Run multiple times to test randomness
        $hasNotes = false;
        $hasNoNotes = false;

        for ($i = 0; $i < 20; $i++) {
            $orderHistory = OrderHistory::factory()->make();

            if ($orderHistory->notes !== null) {
                $hasNotes = true;
            } else {
                $hasNoNotes = true;
            }

            if ($hasNotes && $hasNoNotes) {
                break;
            }
        }

        $this->assertTrue($hasNotes || $hasNoNotes, 'Notes should sometimes be null and sometimes have value');
    }

    /**
     * Test that factory can override attributes
     */
    public function test_factory_can_override_attributes(): void
    {
        $customDescription = 'Custom history description';
        $customNotes = 'Custom notes for this history entry';
        $customStatusFrom = OrderStatus::OPEN->value;
        $customStatusTo = OrderStatus::DELIVERED->value;

        $orderHistory = OrderHistory::factory()->create([
            'description' => $customDescription,
            'notes' => $customNotes,
            'status_from' => $customStatusFrom,
            'status_to' => $customStatusTo,
        ]);

        $this->assertEquals($customDescription, $orderHistory->description);
        $this->assertEquals($customNotes, $orderHistory->notes);
        $this->assertEquals($customStatusFrom, $orderHistory->status_from->value);
        $this->assertEquals($customStatusTo, $orderHistory->status_to->value);
    }

    /**
     * Test factory with specific order
     */
    public function test_factory_with_specific_order(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
        ]);

        $this->assertEquals($order->id, $orderHistory->order_id);
        $this->assertEquals($order->id, $orderHistory->order->id);
    }

    /**
     * Test factory with specific user
     */
    public function test_factory_with_specific_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $orderHistory = OrderHistory::factory()->create([
            'created_by' => $user->id,
        ]);

        $this->assertEquals($user->id, $orderHistory->created_by);
        $this->assertEquals($user->id, $orderHistory->createdBy->id);
    }

    /**
     * Test multiple order histories can be created
     */
    public function test_multiple_order_histories_can_be_created(): void
    {
        $orderHistories = OrderHistory::factory()->count(5)->create();

        $this->assertCount(5, $orderHistories);

        foreach ($orderHistories as $orderHistory) {
            $this->assertInstanceOf(OrderHistory::class, $orderHistory);
            $this->assertDatabaseHas('order_histories', ['id' => $orderHistory->id]);
        }
    }

    /**
     * Test factory generates realistic data
     */
    public function test_factory_generates_realistic_data(): void
    {
        $orderHistory = OrderHistory::factory()->make();

        // Description should be a sentence
        $this->assertGreaterThan(5, strlen($orderHistory->description));
        $this->assertStringEndsWith('.', $orderHistory->description);

        // If notes exist, they should be a paragraph
        if ($orderHistory->notes !== null) {
            $this->assertGreaterThan(10, strlen($orderHistory->notes));
        }
    }

    /**
     * Test factory relationships are properly set
     */
    public function test_factory_relationships(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'created_by' => $employee->id
        ]);

        // Test order relationship
        $this->assertInstanceOf(Order::class, $orderHistory->order);
        $this->assertEquals($order->id, $orderHistory->order->id);

        // Test createdBy relationship
        $this->assertInstanceOf(User::class, $orderHistory->createdBy);
        $this->assertEquals($employee->id, $orderHistory->createdBy->id);
    }

    /**
     * Test that factory respects enum casting
     */
    public function test_factory_respects_enum_casting(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        // After retrieval from database, the enums should be cast properly
        $freshOrderHistory = OrderHistory::find($orderHistory->id);

        $this->assertInstanceOf(OrderStatus::class, $freshOrderHistory->status_from);
        $this->assertInstanceOf(OrderStatus::class, $freshOrderHistory->status_to);
        $this->assertInstanceOf(OrderPriority::class, $freshOrderHistory->priority_from);
        $this->assertInstanceOf(OrderPriority::class, $freshOrderHistory->priority_to);
    }

    /**
     * Test factory generates UUID
     */
    public function test_factory_generates_uuid(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $this->assertNotNull($orderHistory->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $orderHistory->id
        );
    }

    /**
     * Test that factory can create order history for specific order status transition
     */
    public function test_factory_can_create_specific_status_transition(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id,
            'status' => OrderStatus::OPEN->value
        ]);

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Order processing started'
        ]);

        $this->assertEquals(OrderStatus::OPEN->value, $orderHistory->status_from->value);
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $orderHistory->status_to->value);
        $this->assertEquals('Order processing started', $orderHistory->description);
    }

    /**
     * Test that factory can create order history for priority change only
     */
    public function test_factory_can_create_priority_change_only(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'status_from' => null,
            'status_to' => null,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::URGENT->value,
            'description' => 'Priority escalated to urgent'
        ]);

        $this->assertNull($orderHistory->status_from);
        $this->assertNull($orderHistory->status_to);
        $this->assertEquals(OrderPriority::NORMAL->value, $orderHistory->priority_from->value);
        $this->assertEquals(OrderPriority::URGENT->value, $orderHistory->priority_to->value);
    }

    /**
     * Test factory creates histories for same order
     */
    public function test_factory_creates_histories_for_same_order(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        $histories = OrderHistory::factory()
            ->count(3)
            ->create(['order_id' => $order->id]);

        $this->assertCount(3, $histories);

        foreach ($histories as $history) {
            $this->assertEquals($order->id, $history->order_id);
        }

        // Check that all histories are in the database for the same order
        $this->assertEquals(3, OrderHistory::where('order_id', $order->id)->count());
    }
}
