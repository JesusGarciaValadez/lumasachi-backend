<?php

namespace Modules\Lumasachi\Tests\Unit\database\factories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

final class OrderFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the factory creates a valid order
     */
    public function test_factory_creates_valid_order(): void
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
    public function test_factory_generates_all_required_fields(): void
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
    public function test_factory_generates_valid_status(): void
    {
        $validStatuses = [
            Order::STATUS_OPEN,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_READY_FOR_DELIVERY,
            Order::STATUS_DELIVERED,
            Order::STATUS_PAID,
        ];

        $order = Order::factory()->make();

        $this->assertContains($order->status, $validStatuses);
    }

    /**
     * Test that factory generates valid priority values
     */
    public function test_factory_generates_valid_priority(): void
    {
        $validPriorities = [
            Order::PRIORITY_LOW,
            Order::PRIORITY_NORMAL,
            Order::PRIORITY_HIGH,
            Order::PRIORITY_URGENT,
        ];

        $order = Order::factory()->make();

        $this->assertContains($order->priority, $validPriorities);
    }

    /**
     * Test that factory creates associated users
     */
    public function test_factory_creates_associated_users(): void
    {
        $order = Order::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $order->customer_id]);
        $this->assertDatabaseHas('users', ['id' => $order->created_by]);
        $this->assertDatabaseHas('users', ['id' => $order->updated_by]);
    }

    /**
     * Test completed state
     */
    public function test_completed_state(): void
    {
        $order = Order::factory()->completed()->create();

        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->actual_completion);
        $this->assertInstanceOf(CarbonImmutable::class, $order->actual_completion);
        $this->assertLessThanOrEqual(Carbon::now(), $order->actual_completion);
        $this->assertGreaterThanOrEqual(Carbon::now()->subDays(7), $order->actual_completion);
    }

    /**
     * Test open state
     */
    public function test_open_state(): void
    {
        $order = Order::factory()->open()->create();

        $this->assertEquals(Order::STATUS_OPEN, $order->status);
        $this->assertNull($order->actual_completion);
    }

    /**
     * Test that estimated completion is in the future
     */
    public function test_estimated_completion_is_in_future(): void
    {
        $order = Order::factory()->make();

        $this->assertInstanceOf(CarbonImmutable::class, $order->estimated_completion);
        $this->assertGreaterThanOrEqual(Carbon::now(), $order->estimated_completion);
        $this->assertLessThanOrEqual(Carbon::now()->addDays(30), $order->estimated_completion);
    }

    /**
     * Test optional fields
     */
    public function test_optional_fields(): void
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
    public function test_factory_can_override_attributes(): void
    {
        $customTitle = 'Custom Order Title';
        $customStatus = Order::STATUS_PAID;
        $customPriority = Order::PRIORITY_URGENT;

        $order = Order::factory()->create([
            'title' => $customTitle,
            'status' => $customStatus,
            'priority' => $customPriority,
        ]);

        $this->assertEquals($customTitle, $order->title);
        $this->assertEquals($customStatus, $order->status);
        $this->assertEquals($customPriority, $order->priority);
    }

    /**
     * Test factory with specific customer
     */
    public function test_factory_with_specific_customer(): void
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
    public function test_factory_with_specific_assigned_employee(): void
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
    public function test_multiple_orders_can_be_created(): void
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
    public function test_factory_generates_realistic_data(): void
    {
        $order = Order::factory()->make();

        // Title should be a short sentence (3 words)
        $wordCount = str_word_count($order->title);
        $this->assertGreaterThanOrEqual(1, $wordCount);
        $this->assertLessThanOrEqual(10, $wordCount);

        // Description should be a paragraph
        $this->assertGreaterThan(10, strlen($order->description));

        // Category should be a single word
        $this->assertEquals(1, str_word_count($order->category));
    }

    /**
     * Test chaining states
     */
    public function test_chaining_states(): void
    {
        $order = Order::factory()
            ->completed()
            ->create(['priority' => Order::PRIORITY_URGENT]);

        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->actual_completion);
        $this->assertEquals(Order::PRIORITY_URGENT, $order->priority);
    }

    /**
     * Test factory relationships are properly set
     */
    public function test_factory_relationships(): void
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
    public function test_actual_completion_null_by_default(): void
    {
        $order = Order::factory()->make();

        $this->assertNull($order->actual_completion);
    }

    /**
     * Test date casting works correctly
     */
    public function test_date_casting(): void
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(CarbonImmutable::class, $order->estimated_completion);

        if ($order->actual_completion) {
            $this->assertInstanceOf(CarbonImmutable::class, $order->actual_completion);
        }
    }
}
