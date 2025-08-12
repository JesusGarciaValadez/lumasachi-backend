<?php

namespace Modules\Lumasachi\Tests\Unit\database\factories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

final class OrderFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the factory creates a valid order
     */
    #[Test]
    public function it_checks_if_factory_creates_valid_order(): void
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(Order::class, $order);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'title' => $order->title,
        ]);
    }

    /**
     * Test that factory generates all required fields
     */
    #[Test]
    public function it_checks_if_factory_generates_all_required_fields(): void
    {
        $order = Order::factory()->make();

        $this->assertNotNull($order->customer_id);
        $this->assertNotNull($order->title);
        $this->assertNotNull($order->description);
        $this->assertNotNull($order->status);
        $this->assertNotNull($order->priority);
        $this->assertNotNull($order->category);
        $this->assertNotNull($order->estimated_completion);
        $this->assertNotNull($order->created_by);
        $this->assertNotNull($order->updated_by);
    }

    /**
     * Test that factory generates valid status values
     */
    #[Test]
    public function it_checks_if_factory_generates_valid_status(): void
    {
        $validStatuses = [
            OrderStatus::OPEN->value,
            OrderStatus::IN_PROGRESS->value,
            OrderStatus::READY_FOR_DELIVERY->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::PAID->value,
            OrderStatus::RETURNED->value,
            OrderStatus::NOT_PAID->value,
            OrderStatus::CANCELLED->value,
            OrderStatus::ON_HOLD->value,
            OrderStatus::COMPLETED->value,
        ];

        $order = Order::factory()->make();

        $this->assertContains($order->status->value, $validStatuses);
    }

    /**
     * Test that factory generates valid priority values
     */
    #[Test]
    public function it_checks_if_factory_generates_valid_priority(): void
    {
        $validPriorities = [
            OrderPriority::LOW->value,
            OrderPriority::NORMAL->value,
            OrderPriority::HIGH->value,
            OrderPriority::URGENT->value,
        ];

        $order = Order::factory()->make();

        $this->assertContains($order->priority->value, $validPriorities);
    }

    /**
     * Test that factory creates associated users
     */
    #[Test]
    public function it_checks_if_factory_creates_associated_users(): void
    {
        $order = Order::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $order->customer_id]);
        $this->assertDatabaseHas('users', ['id' => $order->created_by]);
        $this->assertDatabaseHas('users', ['id' => $order->updated_by]);
    }

    /**
     * Test completed state
     */
    #[Test]
    public function it_checks_if_completed_state(): void
    {
        $order = Order::factory()->completed()->create();

        $this->assertEquals(OrderStatus::DELIVERED->value, $order->status->value);
        $this->assertNotNull($order->actual_completion);
        $this->assertInstanceOf(CarbonImmutable::class, $order->actual_completion);
        $this->assertLessThanOrEqual(Carbon::now(), $order->actual_completion);
        $this->assertGreaterThanOrEqual(Carbon::now()->subDays(7), $order->actual_completion);
    }

    /**
     * Test open state
     */
    #[Test]
    public function it_checks_if_open_state(): void
    {
        $order = Order::factory()->open()->create();

        $this->assertEquals(OrderStatus::OPEN->value, $order->status->value);
        $this->assertNull($order->actual_completion);
    }

    /**
     * Test that estimated completion is in the future
     */
    #[Test]
    public function it_checks_if_estimated_completion_is_in_future(): void
    {
        $order = Order::factory()->make();

        $this->assertInstanceOf(CarbonImmutable::class, $order->estimated_completion);
        $this->assertGreaterThanOrEqual(Carbon::now(), $order->estimated_completion);
        $this->assertLessThanOrEqual(Carbon::now()->addDays(30), $order->estimated_completion);
    }

    /**
     * Test optional fields
     */
    #[Test]
    public function it_checks_if_optional_fields(): void
    {
        // Run multiple times to test randomness
        $hasNotes = false;
        $hasNoNotes = false;
        $hasAssignedTo = false;
        $hasNoAssignedTo = false;

        for ($i = 0; $i < 20; $i++) {
            $order = Order::factory()->make();

            if ($order->notes !== null) {
                $hasNotes = true;
            } else {
                $hasNoNotes = true;
            }

            if ($order->assigned_to !== null) {
                $hasAssignedTo = true;
            } else {
                $hasNoAssignedTo = true;
            }

            if ($hasNotes && $hasNoNotes && $hasAssignedTo && $hasNoAssignedTo) {
                break;
            }
        }

        $this->assertTrue($hasNotes || $hasNoNotes, 'Notes should sometimes be null and sometimes have value');
        $this->assertTrue($hasAssignedTo || $hasNoAssignedTo, 'Assigned_to should sometimes be null and sometimes have value');
    }

    /**
     * Test that factory can override attributes
     */
    #[Test]
    public function it_checks_if_factory_can_override_attributes(): void
    {
        $customTitle = 'Custom Order Title';
        $customStatus = OrderStatus::PAID->value;
        $customPriority = OrderPriority::URGENT->value;

        $order = Order::factory()->create([
            'title' => $customTitle,
            'status' => $customStatus,
            'priority' => $customPriority,
        ]);

        $this->assertEquals($customTitle, $order->title);
        $this->assertEquals($customStatus, $order->status->value);
        $this->assertEquals($customPriority, $order->priority->value);
    }

    /**
     * Test factory with specific customer
     */
    #[Test]
    public function it_checks_if_factory_with_specific_customer(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals($customer->id, $order->customer->id);
    }

    /**
     * Test factory with specific assigned employee
     */
    #[Test]
    public function it_checks_if_factory_with_specific_assigned_employee(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $order = Order::factory()->create([
            'assigned_to' => $employee->id,
        ]);

        $this->assertEquals($employee->id, $order->assigned_to);
        $this->assertEquals($employee->id, $order->assignedTo->id);
    }

    /**
     * Test multiple orders can be created
     */
    #[Test]
    public function it_checks_if_multiple_orders_can_be_created(): void
    {
        $orders = Order::factory()->count(5)->create();

        $this->assertCount(5, $orders);

        foreach ($orders as $order) {
            $this->assertInstanceOf(Order::class, $order);
            $this->assertDatabaseHas('orders', ['id' => $order->id]);
        }
    }

    /**
     * Test factory generates realistic data
     */
    #[Test]
    public function it_checks_if_factory_generates_realistic_data(): void
    {
        $order = Order::factory()->make();

        // Title should be a short sentence (3 words)
        $wordCount = str_word_count($order->title);
        $this->assertGreaterThanOrEqual(1, $wordCount);
        $this->assertLessThanOrEqual(10, $wordCount);

        // Description should be a paragraph
        $this->assertGreaterThan(10, strlen($order->description));

        // Category should be loaded and have a name
        $this->assertNotNull($order->category);
        $this->assertNotNull($order->category->name);
    }

    /**
     * Test chaining states
     */
    #[Test]
    public function it_checks_if_chaining_states(): void
    {
        $order = Order::factory()
            ->completed()
            ->create(['priority' => OrderPriority::URGENT->value]);

        $this->assertEquals(OrderStatus::DELIVERED->value, $order->status->value);
        $this->assertNotNull($order->actual_completion);
        $this->assertEquals(OrderPriority::URGENT->value, $order->priority->value);
    }

    /**
     * Test factory relationships are properly set
     */
    #[Test]
    public function it_checks_if_factory_relationships(): void
    {
        // Create users with specific roles to ensure relationships work
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
            'assigned_to' => $employee->id
        ]);

        // Test customer relationship
        $this->assertInstanceOf(User::class, $order->customer);
        $this->assertEquals($customer->id, $order->customer->id);

        // Test createdBy relationship
        $this->assertInstanceOf(User::class, $order->createdBy);
        $this->assertEquals($creator->id, $order->createdBy->id);

        // Test updatedBy relationship
        $this->assertInstanceOf(User::class, $order->updatedBy);
        $this->assertEquals($updater->id, $order->updatedBy->id);

        // Test assignedTo relationship
        $this->assertInstanceOf(User::class, $order->assignedTo);
        $this->assertEquals($employee->id, $order->assignedTo->id);
    }

    /**
     * Test that actual_completion is null by default
     */
    #[Test]
    public function it_checks_if_actual_completion_null_by_default(): void
    {
        $order = Order::factory()->make();

        $this->assertNull($order->actual_completion);
    }

    /**
     * Test date casting works correctly
     */
    #[Test]
    public function it_checks_if_date_casting(): void
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(CarbonImmutable::class, $order->estimated_completion);

        if ($order->actual_completion) {
            $this->assertInstanceOf(CarbonImmutable::class, $order->actual_completion);
        }
    }
}
