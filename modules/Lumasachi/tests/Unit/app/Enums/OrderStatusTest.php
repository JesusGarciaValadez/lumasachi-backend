<?php

namespace Modules\Lumasachi\tests\Unit\app\Enums;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all OrderStatus enum values are correctly defined.
     */
    #[Test]
    public function all_order_status_enum_values_are_defined()
    {
        $statuses = OrderStatus::cases();

        $this->assertCount(8, $statuses);

        $expectedStatuses = [
            'OPEN' => 'Open',
            'IN_PROGRESS' => 'In Progress',
            'READY_FOR_DELIVERY' => 'Ready for delivery',
            'DELIVERED' => 'Delivered',
            'PAID' => 'Paid',
            'RETURNED' => 'Returned',
            'NOT_PAID' => 'Not paid',
            'CANCELLED' => 'Cancelled'
        ];

        foreach ($statuses as $status) {
            $this->assertArrayHasKey($status->name, $expectedStatuses);
            $this->assertEquals($expectedStatuses[$status->name], $status->value);
        }
    }

    /**
     * Test getStatuses static method returns all status values.
     */
    #[Test]
    public function get_statuses_returns_all_values()
    {
        $statuses = OrderStatus::getStatuses();

        $this->assertIsArray($statuses);
        $this->assertCount(8, $statuses);
        $this->assertEquals(['Open', 'In Progress', 'Ready for delivery', 'Delivered', 'Paid', 'Returned', 'Not paid', 'Cancelled'], $statuses);
    }

    /**
     * Test getLabel method returns correct labels for each status.
     */
    #[Test]
    public function get_label_returns_correct_labels()
    {
        $testCases = [
            ['status' => OrderStatus::OPEN, 'expected' => 'Open'],
            ['status' => OrderStatus::IN_PROGRESS, 'expected' => 'In Progress'],
            ['status' => OrderStatus::READY_FOR_DELIVERY, 'expected' => 'Ready for delivery'],
            ['status' => OrderStatus::DELIVERED, 'expected' => 'Delivered'],
            ['status' => OrderStatus::PAID, 'expected' => 'Paid'],
            ['status' => OrderStatus::RETURNED, 'expected' => 'Returned'],
            ['status' => OrderStatus::NOT_PAID, 'expected' => 'Not paid'],
            ['status' => OrderStatus::CANCELLED, 'expected' => 'Cancelled'],
        ];

        foreach ($testCases as $testCase) {
            $this->assertEquals(
                $testCase['expected'],
                $testCase['status']->getLabel(),
                "Status {$testCase['status']->name} should have label: {$testCase['expected']}"
            );
        }
    }

    /**
     * Test that all status values can be stored in the database.
     */
    #[Test]
    public function all_status_values_can_be_stored_in_database()
    {
        $user = User::factory()->create();

        foreach (OrderStatus::cases() as $status) {
            $order = Order::create([
                'customer_id' => $user->id,
                'title' => 'Test Order with ' . $status->value . ' status',
                'description' => 'Testing status: ' . $status->value,
                'status' => $status->value,
                'priority' => 'Normal',
                'created_by' => $user->id
            ]);

            $this->assertNotNull($order);
            $this->assertEquals($status->value, $order->status);

            // Verify it's stored correctly in the database
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'status' => $status->value
            ]);
        }
    }

    /**
     * Test that invalid status values are rejected by the database.
     */
    #[Test]
    public function invalid_status_values_are_rejected()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $user = User::factory()->create();

        Order::create([
            'customer_id' => $user->id,
            'title' => 'Test Order with Invalid Status',
            'description' => 'This should fail',
            'status' => 'InvalidStatus', // This should fail
            'priority' => 'Normal',
            'created_by' => $user->id
        ]);
    }

    /**
     * Test status enum value comparison.
     */
    #[Test]
    public function status_enum_value_comparison()
    {
        $openStatus = OrderStatus::OPEN;
        $inProgressStatus = OrderStatus::IN_PROGRESS;
        $deliveredStatus = OrderStatus::DELIVERED;

        // Test same status comparison
        $this->assertTrue($openStatus === OrderStatus::OPEN);
        $this->assertTrue($inProgressStatus === OrderStatus::IN_PROGRESS);

        // Test different status comparison
        $this->assertFalse($openStatus === $deliveredStatus);
        $this->assertFalse($inProgressStatus === $deliveredStatus);
    }

    /**
     * Test status enum can be used with match expressions.
     */
    #[Test]
    public function status_enum_with_match_expression()
    {
        $testCases = [
            ['status' => OrderStatus::OPEN, 'expectedHours' => 48],
            ['status' => OrderStatus::IN_PROGRESS, 'expectedHours' => 24],
            ['status' => OrderStatus::READY_FOR_DELIVERY, 'expectedHours' => 8],
            ['status' => OrderStatus::DELIVERED, 'expectedHours' => 0],
        ];

        foreach ($testCases as $testCase) {
            $hoursToComplete = match ($testCase['status']) {
                OrderStatus::OPEN => 48,
                OrderStatus::IN_PROGRESS => 24,
                OrderStatus::READY_FOR_DELIVERY => 8,
                OrderStatus::DELIVERED => 0,
                default => null,
            };

            $this->assertEquals(
                $testCase['expectedHours'],
                $hoursToComplete,
                "Status {$testCase['status']->value} should have {$testCase['expectedHours']} hours to complete"
            );
        }
    }

    /**
     * Test that OrderStatus enum values are properly serialized to JSON.
     */
    #[Test]
    public function status_enum_json_serialization()
    {
        $user = User::factory()->create();

        $order = Order::create([
            'customer_id' => $user->id,
            'title' => 'Test Order for JSON',
            'description' => 'Testing JSON serialization',
            'status' => OrderStatus::PAID->value,
            'priority' => 'Normal',
            'created_by' => $user->id
        ]);

        $jsonData = $order->toJson();
        $this->assertStringContainsString('"status":"Paid"', $jsonData);

        $arrayData = $order->toArray();
        $this->assertEquals('Paid', $arrayData['status']);
    }

    /**
     * Test creating order with each status using the enum directly.
     */
    #[Test]
    public function create_order_with_enum_values()
    {
        $user = User::factory()->create();

        foreach (OrderStatus::cases() as $status) {
            $order = new Order();
            $order->customer_id = $user->id;
            $order->title = 'Order with ' . $status->value;
            $order->description = 'Testing enum value assignment';
            $order->status = $status->value;
            $order->priority = 'Normal';
            $order->created_by = $user->id;
            $order->save();

            $this->assertEquals($status->value, $order->fresh()->status);
        }
    }

    /**
     * Test that all enum cases have unique values.
     */
    #[Test]
    public function all_status_values_are_unique()
    {
        $values = OrderStatus::getStatuses();
        $uniqueValues = array_unique($values);

        $this->assertCount(
            count($values),
            $uniqueValues,
            'All status values should be unique'
        );
    }
}

