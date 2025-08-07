<?php

namespace Modules\Lumasachi\tests\Unit\app\Enums;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class OrderPriorityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all OrderPriority enum values are correctly defined.
     */
    #[Test]
    public function it_checks_all_order_priority_enum_values_are_defined()
    {
        $priorities = OrderPriority::cases();

        $this->assertCount(4, $priorities);

        $expectedPriorities = [
            'LOW' => 'Low',
            'NORMAL' => 'Normal',
            'HIGH' => 'High',
            'URGENT' => 'Urgent'
        ];

        foreach ($priorities as $priority) {
            $this->assertArrayHasKey($priority->name, $expectedPriorities);
            $this->assertEquals($expectedPriorities[$priority->name], $priority->value);
        }
    }

    /**
     * Test getPriorities static method returns all priority values.
     */
    #[Test]
    public function it_checks_get_priorities_returns_all_values()
    {
        $priorities = OrderPriority::getPriorities();

        $this->assertIsArray($priorities);
        $this->assertCount(4, $priorities);
        $this->assertEquals(['Low', 'Normal', 'High', 'Urgent'], $priorities);
    }

    /**
     * Test getLabel method returns correct labels for each priority.
     */
    #[Test]
    public function it_checks_get_label_returns_correct_labels()
    {
        $testCases = [
            ['priority' => OrderPriority::LOW->value, 'expected' => 'Low'],
            ['priority' => OrderPriority::NORMAL->value, 'expected' => 'Normal'],
            ['priority' => OrderPriority::HIGH->value, 'expected' => 'High'],
            ['priority' => OrderPriority::URGENT->value, 'expected' => 'Urgent'],
        ];

        foreach ($testCases as $testCase) {
            $this->assertEquals(
                $testCase['expected'],
                OrderPriority::from($testCase['priority'])->getLabel(),
                "Priority {$testCase['priority']} should have label: {$testCase['expected']}"
            );
        }
    }

    /**
     * Test that all priority values can be stored in the database.
     */
    #[Test]
    public function it_checks_all_priority_values_can_be_stored_in_database()
    {
        $user = User::factory()->create();

        foreach (OrderPriority::cases() as $priority) {
            $order = Order::create([
                'customer_id' => $user->id,
                'title' => 'Test Order with ' . $priority->value . ' priority',
                'description' => 'Testing priority: ' . $priority->value,
                'status' => 'Open',
                'priority' => $priority->value,
                'created_by' => $user->id
            ]);

            $this->assertNotNull($order);
            $this->assertEquals($priority->value, $order->priority);

            // Verify it's stored correctly in the database
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'priority' => $priority->value
            ]);
        }
    }

    /**
     * Test that invalid priority values are rejected by the database.
     */
    #[Test]
    public function it_checks_invalid_priority_values_are_rejected()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $user = User::factory()->create();

        Order::create([
            'customer_id' => $user->id,
            'title' => 'Test Order with Invalid Priority',
            'description' => 'This should fail',
            'status' => 'Open',
            'priority' => 'InvalidPriority', // This should fail
            'created_by' => $user->id
        ]);
    }

    /**
     * Test priority enum value comparison.
     */
    #[Test]
    public function it_checks_priority_enum_value_comparison()
    {
        $lowPriority = OrderPriority::LOW;
        $normalPriority = OrderPriority::NORMAL;
        $highPriority = OrderPriority::HIGH;
        $urgentPriority = OrderPriority::URGENT;

        // Test same priority comparison
        $this->assertTrue($lowPriority === OrderPriority::LOW);
        $this->assertTrue($normalPriority === OrderPriority::NORMAL);

        // Test different priority comparison
        $this->assertFalse($lowPriority === $highPriority);
        $this->assertFalse($normalPriority === $urgentPriority);
    }

    /**
     * Test priority enum can be used with match expressions.
     */
    #[Test]
    public function it_checks_priority_enum_with_match_expression()
    {
        $testCases = [
            ['priority' => OrderPriority::LOW->value, 'expectedDays' => 7],
            ['priority' => OrderPriority::NORMAL->value, 'expectedDays' => 3],
            ['priority' => OrderPriority::HIGH->value, 'expectedDays' => 1],
            ['priority' => OrderPriority::URGENT->value, 'expectedDays' => 0],
        ];

        foreach ($testCases as $testCase) {
            $daysToComplete = match ($testCase['priority']) {
                OrderPriority::LOW->value => 7,
                OrderPriority::NORMAL->value => 3,
                OrderPriority::HIGH->value => 1,
                OrderPriority::URGENT->value => 0,
            };

            $this->assertEquals(
                $testCase['expectedDays'],
                $daysToComplete,
                "Priority {$testCase['priority']} should have {$testCase['expectedDays']} days to complete"
            );
        }
    }

    /**
     * Test that OrderPriority enum values are properly serialized to JSON.
     */
    #[Test]
    public function it_checks_priority_enum_json_serialization()
    {
        $user = User::factory()->create();

        $order = Order::create([
            'customer_id' => $user->id,
            'title' => 'Test Order for JSON',
            'description' => 'Testing JSON serialization',
            'status' => 'Open',
            'priority' => OrderPriority::HIGH->value,
            'created_by' => $user->id
        ]);

        $jsonData = $order->toJson();
        $this->assertStringContainsString('"priority":"High"', $jsonData);

        $arrayData = $order->toArray();
        $this->assertEquals('High', $arrayData['priority']);
    }

    /**
     * Test creating order with each priority using the enum directly.
     */
    #[Test]
    public function it_checks_create_order_with_enum_values()
    {
        $user = User::factory()->create();

        foreach (OrderPriority::cases() as $priority) {
            $order = new Order();
            $order->customer_id = $user->id;
            $order->title = 'Order with ' . $priority->value;
            $order->description = 'Testing enum value assignment';
            $order->status = 'Open';
            $order->priority = $priority->value;
            $order->created_by = $user->id;
            $order->save();

            $this->assertEquals($priority->value, $order->fresh()->priority);
        }
    }

    /**
     * Test that all enum cases have unique values.
     */
    #[Test]
    public function it_checks_all_priority_values_are_unique()
    {
        $values = OrderPriority::getPriorities();
        $uniqueValues = array_unique($values);

        $this->assertCount(
            count($values),
            $uniqueValues,
            'All priority values should be unique'
        );
    }

    /**
     * Test priority ordering logic (conceptual test).
     */
    #[Test]
    public function it_checks_priority_ordering_concept()
    {
        // Define expected priority order (from lowest to highest priority)
        $priorityOrder = [
            OrderPriority::LOW->value => 1,
            OrderPriority::NORMAL->value => 2,
            OrderPriority::HIGH->value => 3,
            OrderPriority::URGENT->value => 4,
        ];

        // Verify that URGENT has higher priority value than HIGH
        $this->assertGreaterThan(
            $priorityOrder[OrderPriority::HIGH->value],
            $priorityOrder[OrderPriority::URGENT->value]
        );

        // Verify that LOW has lower priority value than NORMAL
        $this->assertLessThan(
            $priorityOrder[OrderPriority::NORMAL->value],
            $priorityOrder[OrderPriority::LOW->value]
        );
    }
}
