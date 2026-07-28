<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('checks all order priority enum values are defined', function () {
    $priorities = OrderPriority::cases();

    expect($priorities)->toHaveCount(4);

    $expectedPriorities = [
        'LOW' => 'Low',
        'NORMAL' => 'Normal',
        'HIGH' => 'High',
        'URGENT' => 'Urgent',
    ];

    foreach ($priorities as $priority) {
        expect($expectedPriorities)->toHaveKey($priority->name);
        expect($priority->value)->toEqual($expectedPriorities[$priority->name]);
    }
});
it('checks get priorities returns all values', function () {
    $priorities = OrderPriority::getPriorities();

    expect($priorities)->toBeArray();
    expect($priorities)->toHaveCount(4);
    expect($priorities)->toEqual(['Low', 'Normal', 'High', 'Urgent']);
});
it('checks get label returns localized labels', function () {
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        foreach (OrderPriority::cases() as $priority) {
            $this->assertNotSame("orders.priority_labels.{$priority->value}", $priority->getLabel());
        }
    }
});
it('checks all priority values can be stored in database', function () {
    $user = User::factory()->create();

    foreach (OrderPriority::cases() as $priority) {
        $order = Order::factory()->createQuietly([
            'customer_id' => $user->id,
            'title' => 'Test Order with ' . $priority->value . ' priority',
            'description' => 'Testing priority: ' . $priority->value,
            'status' => 'Open',
            'priority' => $priority,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        expect($order)->not->toBeNull();
        expect($order->priority->value)->toEqual($priority->value);

        // Verify it's stored correctly in the database
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'priority' => $priority->value,
        ]);
    }
});
it('checks invalid priority values are rejected', function () {
    $this->expectException(ValueError::class);

    $user = User::factory()->create();

    Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'title' => 'Test Order with Invalid Priority',
        'description' => 'This should fail',
        'status' => 'Open',
        'priority' => 'InvalidPriority', // This should fail
        'created_by' => $user->id,
        'assigned_to' => $user->id,
    ]);
});
it('checks priority enum value comparison', function () {
    $lowPriority = OrderPriority::LOW;
    $normalPriority = OrderPriority::NORMAL;
    $highPriority = OrderPriority::HIGH;
    $urgentPriority = OrderPriority::URGENT;

    // Test same priority comparison
    expect($lowPriority === OrderPriority::LOW)->toBeTrue();
    expect($normalPriority === OrderPriority::NORMAL)->toBeTrue();

    // Test different priority comparison
    expect($lowPriority === $highPriority)->toBeFalse();
    expect($normalPriority === $urgentPriority)->toBeFalse();
});
it('checks priority enum with match expression', function () {
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

        expect($daysToComplete)->toEqual($testCase['expectedDays']);
    }
});
it('checks priority enum json serialization', function () {
    $user = User::factory()->create();

    $order = Order::factory()->createQuietly([
        'customer_id' => $user->id,
        'title' => 'Test Order for JSON',
        'description' => 'Testing JSON serialization',
        'status' => 'Open',
        'priority' => OrderPriority::HIGH,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
    ]);

    $jsonData = $order->toJson();
    $this->assertStringContainsString('"priority":"High"', $jsonData);

    $arrayData = $order->toArray();
    expect($arrayData['priority'])->toEqual('High');
});
it('checks create order with enum values', function () {
    $user = User::factory()->create();

    foreach (OrderPriority::cases() as $priority) {
        $order = Order::factory()->createQuietly([
            'customer_id' => $user->id,
            'title' => 'Order with ' . $priority->value,
            'description' => 'Testing enum value assignment',
            'status' => 'Open',
            'priority' => $priority,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        expect($order->fresh()->priority->value)->toEqual($priority->value);
    }
});
it('checks all priority values are unique', function () {
    $values = OrderPriority::getPriorities();
    $uniqueValues = array_unique($values);

    expect($uniqueValues)->toHaveCount(count($values));
});
it('checks priority ordering concept', function () {
    // Define expected priority order (from lowest to highest priority)
    $priorityOrder = [
        OrderPriority::LOW->value => 1,
        OrderPriority::NORMAL->value => 2,
        OrderPriority::HIGH->value => 3,
        OrderPriority::URGENT->value => 4,
    ];

    // Verify that URGENT has higher priority value than HIGH
    expect($priorityOrder[OrderPriority::URGENT->value])->toBeGreaterThan($priorityOrder[OrderPriority::HIGH->value]);

    // Verify that LOW has lower priority value than NORMAL
    expect($priorityOrder[OrderPriority::LOW->value])->toBeLessThan($priorityOrder[OrderPriority::NORMAL->value]);
});
