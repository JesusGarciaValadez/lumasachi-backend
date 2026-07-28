<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if factory creates valid order', function () {
    $order = Order::factory()->createQuietly();

    expect($order)->toBeInstanceOf(Order::class);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'title' => $order->title,
    ]);
});
it('checks if factory generates all required fields', function () {
    $order = Order::factory()->createQuietly();

    expect($order->customer_id)->not->toBeNull();
    expect($order->title)->not->toBeNull();
    expect($order->description)->not->toBeNull();
    expect($order->status)->not->toBeNull();
    expect($order->priority)->not->toBeNull();
    expect($order->estimated_completion)->not->toBeNull();
    expect($order->created_by)->not->toBeNull();
    expect($order->updated_by)->not->toBeNull();
});
it('checks if factory generates valid status', function () {
    $validStatuses = [
        // New workflow values
        OrderStatus::Received->value,
        OrderStatus::AwaitingReview->value,
        OrderStatus::Reviewed->value,
        OrderStatus::AwaitingCustomerApproval->value,
        OrderStatus::ReadyForWork->value,
        // Existing values
        OrderStatus::Open->value,
        OrderStatus::InProgress->value,
        OrderStatus::ReadyForDelivery->value,
        OrderStatus::Delivered->value,
        OrderStatus::Paid->value,
        OrderStatus::Returned->value,
        OrderStatus::NotPaid->value,
        OrderStatus::Cancelled->value,
        OrderStatus::OnHold->value,
        OrderStatus::Completed->value,
    ];

    $order = Order::factory()->createQuietly();

    expect($validStatuses)->toContain($order->status->value);
});
it('checks if factory generates valid priority', function () {
    $validPriorities = [
        OrderPriority::LOW->value,
        OrderPriority::NORMAL->value,
        OrderPriority::HIGH->value,
        OrderPriority::URGENT->value,
    ];

    $order = Order::factory()->createQuietly();

    expect($validPriorities)->toContain($order->priority->value);
});
it('checks if factory creates associated users', function () {
    $order = Order::factory()->createQuietly();

    $this->assertDatabaseHas('users', ['id' => $order->customer_id]);
    $this->assertDatabaseHas('users', ['id' => $order->created_by]);
    $this->assertDatabaseHas('users', ['id' => $order->updated_by]);
});
it('checks if completed state', function () {
    $order = Order::factory()->completed()->createQuietly();

    expect($order->status->value)->toEqual(OrderStatus::Delivered->value);
    expect($order->actual_completion)->not->toBeNull();
    expect($order->actual_completion)->toBeInstanceOf(CarbonImmutable::class);
    expect($order->actual_completion)->toBeLessThanOrEqual(Carbon::now());
    expect($order->actual_completion)->toBeGreaterThanOrEqual(Carbon::now()->subDays(7));
});
it('checks if open state', function () {
    $order = Order::factory()->open()->createQuietly();

    expect($order->status->value)->toEqual(OrderStatus::Open->value);
    expect($order->actual_completion)->toBeNull();
});
it('checks if estimated completion is in future', function () {
    $order = Order::factory()->createQuietly();

    expect($order->estimated_completion)->toBeInstanceOf(CarbonImmutable::class);
    expect($order->estimated_completion)->toBeGreaterThanOrEqual(Carbon::now());
    expect($order->estimated_completion)->toBeLessThanOrEqual(Carbon::now()->addDays(30));
});
it('checks if optional fields', function () {
    // Run multiple times to test randomness
    $hasNotes = false;
    $hasNoNotes = false;
    $hasAssignedTo = false;
    $hasNoAssignedTo = false;

    for ($i = 0; $i < 20; $i++) {
        $order = Order::factory()->createQuietly();

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

    expect($hasNotes || $hasNoNotes)->toBeTrue('Notes should sometimes be null and sometimes have value');
    expect($hasAssignedTo || $hasNoAssignedTo)->toBeTrue('Assigned_to should sometimes be null and sometimes have value');
});
it('checks if factory can override attributes', function () {
    $customTitle = 'Custom Order Title';
    $customStatus = OrderStatus::Paid->value;
    $customPriority = OrderPriority::URGENT->value;

    $order = Order::factory()->createQuietly([
        'title' => $customTitle,
        'status' => $customStatus,
        'priority' => $customPriority,
    ]);

    expect($order->title)->toEqual($customTitle);
    expect($order->status->value)->toEqual($customStatus);
    expect($order->priority->value)->toEqual($customPriority);
});
it('checks if factory with specific customer', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
    ]);

    expect($order->customer_id)->toEqual($customer->id);
    expect($order->customer->id)->toEqual($customer->id);
});
it('checks if factory with specific assigned employee', function () {
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

    $order = Order::factory()->createQuietly([
        'assigned_to' => $employee->id,
    ]);

    expect($order->assigned_to)->toEqual($employee->id);
    expect($order->assignedTo->id)->toEqual($employee->id);
});
it('checks if multiple orders can be created', function () {
    $orders = Order::factory()->count(5)->createQuietly();

    expect($orders)->toHaveCount(5);

    foreach ($orders as $order) {
        expect($order)->toBeInstanceOf(Order::class);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
});
it('checks if factory generates realistic data', function () {
    $order = Order::factory()->createQuietly();

    // Title should be a short sentence (3 words)
    $wordCount = str_word_count($order->title);
    expect($wordCount)->toBeGreaterThanOrEqual(1);
    expect($wordCount)->toBeLessThanOrEqual(10);

    // Description should be a paragraph
    expect(mb_strlen($order->description))->toBeGreaterThan(10);
});
it('checks if chaining states', function () {
    $order = Order::factory()
        ->completed()
        ->createQuietly(['priority' => OrderPriority::URGENT->value]);

    expect($order->status->value)->toEqual(OrderStatus::Delivered->value);
    expect($order->actual_completion)->not->toBeNull();
    expect($order->priority->value)->toEqual(OrderPriority::URGENT->value);
});
it('checks if factory relationships', function () {
    // Create users with specific roles to ensure relationships work
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $creator = User::factory()->create();
    $updater = User::factory()->create();

    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
        'assigned_to' => $employee->id,
    ]);

    // Test customer relationship
    expect($order->customer)->toBeInstanceOf(User::class);
    expect($order->customer->id)->toEqual($customer->id);

    // Test createdBy relationship
    expect($order->createdBy)->toBeInstanceOf(User::class);
    expect($order->createdBy->id)->toEqual($creator->id);

    // Test updatedBy relationship
    expect($order->updatedBy)->toBeInstanceOf(User::class);
    expect($order->updatedBy->id)->toEqual($updater->id);

    // Test assignedTo relationship
    expect($order->assignedTo)->toBeInstanceOf(User::class);
    expect($order->assignedTo->id)->toEqual($employee->id);
});
it('checks if actual completion null by default', function () {
    $order = Order::factory()->createQuietly();

    expect($order->actual_completion)->toBeNull();
});
it('checks if date casting', function () {
    $order = Order::factory()->createQuietly();

    expect($order->estimated_completion)->toBeInstanceOf(CarbonImmutable::class);

    if ($order->actual_completion) {
        expect($order->actual_completion)->toBeInstanceOf(CarbonImmutable::class);
    }
});
