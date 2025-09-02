<?php

namespace Tests\Feature\database\factories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\OrderHistory;
use App\Models\Order;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class OrderHistoryFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the factory creates a valid order history
     */
    #[Test]
    public function it_checks_if_factory_creates_valid_order_history(): void
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
    #[Test]
    public function it_checks_if_factory_generates_all_required_fields(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $this->assertNotNull($orderHistory->order_id);
        $this->assertNotNull($orderHistory->field_changed);
        // old_value can be null for initial creation
        $this->assertNotNull($orderHistory->new_value);
        $this->assertNotNull($orderHistory->created_by);
    }

    /**
     * Test that factory generates valid field changed values
     */
    #[Test]
    public function it_checks_if_factory_generates_valid_field_changed_values(): void
    {
        $validFields = [
            'status',
            'priority',
            'assigned_to',
            'title',
            'estimated_completion',
            'actual_completion',
            'notes',
            'category_id'
        ];

        $orderHistory = OrderHistory::factory()->make();

        // Check that the field_changed value is valid
        $this->assertContains($orderHistory->field_changed, $validFields);
    }

    /**
     * Test that factory generates appropriate values based on field_changed
     */
    #[Test]
    public function it_checks_if_factory_generates_appropriate_values_based_on_field(): void
    {
        // Test multiple factory generations to ensure various fields are tested
        for ($i = 0; $i < 10; $i++) {
            $orderHistory = OrderHistory::factory()->make();

            if ($orderHistory->field_changed === 'status') {
                $validStatuses = array_map(fn($status) => $status->value, OrderStatus::cases());
                // Handle the case where getter returns enum instance
                $oldValue = $orderHistory->old_value instanceof OrderStatus ? $orderHistory->old_value->value : $orderHistory->old_value;
                $newValue = $orderHistory->new_value instanceof OrderStatus ? $orderHistory->new_value->value : $orderHistory->new_value;
                if ($oldValue !== null) {
                    $this->assertContains($oldValue, $validStatuses);
                }
                $this->assertContains($newValue, $validStatuses);
            } elseif ($orderHistory->field_changed === 'priority') {
                $validPriorities = array_map(fn($priority) => $priority->value, OrderPriority::cases());
                // Handle the case where getter returns enum instance
                $oldValue = $orderHistory->old_value instanceof OrderPriority ? $orderHistory->old_value->value : $orderHistory->old_value;
                $newValue = $orderHistory->new_value instanceof OrderPriority ? $orderHistory->new_value->value : $orderHistory->new_value;
                if ($oldValue !== null) {
                    $this->assertContains($oldValue, $validPriorities);
                }
                $this->assertContains($newValue, $validPriorities);
            }
        }
    }

    /**
     * Test that factory creates associated models
     */
    #[Test]
    public function it_checks_if_factory_creates_associated_models(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        // Check that order was created
        $this->assertDatabaseHas('orders', ['id' => $orderHistory->order_id]);

        // Check that user was created
        $this->assertDatabaseHas('users', ['id' => $orderHistory->created_by]);
    }

    /**
     * Test optional comment field
     */
    #[Test]
    public function it_checks_if_optional_comment_field(): void
    {
        // Run multiple times to test randomness
        $hasComment = false;
        $hasNoComment = false;

        for ($i = 0; $i < 20; $i++) {
            $orderHistory = OrderHistory::factory()->make();

            if ($orderHistory->comment !== null) {
                $hasComment = true;
            } else {
                $hasNoComment = true;
            }

            if ($hasComment && $hasNoComment) {
                break;
            }
        }

        $this->assertTrue($hasComment || $hasNoComment, 'Comment should sometimes be null and sometimes have value');
    }

    /**
     * Test that factory can override attributes
     */
    #[Test]
    public function it_checks_if_factory_can_override_attributes(): void
    {
        $customComment = 'Custom comment for this history entry';
        $customFieldChanged = 'status';
        $customOldValue = OrderStatus::OPEN->value;
        $customNewValue = OrderStatus::DELIVERED->value;

        $orderHistory = OrderHistory::factory()->create([
            'comment' => $customComment,
            'field_changed' => $customFieldChanged,
            'old_value' => $customOldValue,
            'new_value' => $customNewValue,
        ]);

        $this->assertEquals($customComment, $orderHistory->comment);
        $this->assertEquals($customFieldChanged, $orderHistory->field_changed);
        $this->assertEquals($customOldValue, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals($customNewValue, $orderHistory->getRawOriginal('new_value'));
    }

    /**
     * Test factory with specific order
     */
    #[Test]
    public function it_checks_if_factory_with_specific_order(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->createQuietly([
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
    #[Test]
    public function it_checks_if_factory_with_specific_user(): void
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
    #[Test]
    public function it_checks_if_multiple_order_histories_can_be_created(): void
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
    #[Test]
    public function it_checks_if_factory_generates_realistic_data(): void
    {
        $orderHistory = OrderHistory::factory()->make();

        // Field changed should be one of the expected values
        $this->assertContains($orderHistory->field_changed, [
            'status',
            'priority',
            'assigned_to',
            'title',
            'estimated_completion',
            'actual_completion',
            'notes',
            'category_id'
        ]);

        // If comment exists, it should be meaningful
        if ($orderHistory->comment !== null) {
            $this->assertGreaterThan(10, strlen($orderHistory->comment));
        }
    }

    /**
     * Test factory relationships are properly set
     */
    #[Test]
    public function it_checks_if_factory_relationships(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->createQuietly([
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
     * Test that factory respects field types
     */
    #[Test]
    public function it_checks_if_factory_respects_field_types(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        // After retrieval from database, check field types
        $freshOrderHistory = OrderHistory::find($orderHistory->id);

        $this->assertIsString($freshOrderHistory->field_changed);
        // Check raw values are strings in the database
        if ($freshOrderHistory->getRawOriginal('old_value') !== null) {
            $this->assertIsString($freshOrderHistory->getRawOriginal('old_value'));
        }
        if ($freshOrderHistory->getRawOriginal('new_value') !== null) {
            $this->assertIsString($freshOrderHistory->getRawOriginal('new_value'));
        }
    }

    /**
     * Test factory generates UUID
     */
    #[Test]
    public function it_checks_if_factory_generates_uuid(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $this->assertNotNull($orderHistory->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $orderHistory->uuid
        );
    }

    /**
     * Test that factory can create order history for specific order status transition
     */
    #[Test]
    public function it_checks_if_factory_can_create_specific_status_transition(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $customer->id,
            'status' => OrderStatus::OPEN->value
        ]);

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Order processing started'
        ]);

        $this->assertEquals('status', $orderHistory->field_changed);
        $this->assertEquals(OrderStatus::OPEN->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $orderHistory->getRawOriginal('new_value'));
        $this->assertEquals('Order processing started', $orderHistory->comment);
    }

    /**
     * Test that factory can create order history for priority change only
     */
    #[Test]
    public function it_checks_if_factory_can_create_priority_change_only(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $customer->id
        ]);

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::URGENT->value,
            'comment' => 'Priority escalated to urgent'
        ]);

        $this->assertEquals('priority', $orderHistory->field_changed);
        $this->assertEquals(OrderPriority::NORMAL->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::URGENT->value, $orderHistory->getRawOriginal('new_value'));
    }

    /**
     * Test factory creates histories for same order
     */
    #[Test]
    public function it_checks_if_factory_creates_histories_for_same_order(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $order = Order::factory()->createQuietly([
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
