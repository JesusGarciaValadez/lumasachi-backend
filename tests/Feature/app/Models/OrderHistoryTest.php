<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create test users
    $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

    // Create test order
    $this->order = Order::factory()->createQuietly([
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Open->value,
        'priority' => OrderPriority::NORMAL->value,
    ]);
});
it('checks if can create order history with valid data', function () {
    $orderHistory = OrderHistory::create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Status changed to in progress - Customer requested urgent processing',
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory)->toBeInstanceOf(OrderHistory::class);
    $this->assertDatabaseHas('order_histories', [
        'id' => $orderHistory->id,
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Status changed to in progress - Customer requested urgent processing',
    ]);
});
it('checks if order history relationships', function () {
    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
    ]);

    // Test order relationship
    expect($orderHistory->order)->toBeInstanceOf(Order::class);
    expect($orderHistory->order->id)->toEqual($this->order->id);

    // Test createdBy relationship
    expect($orderHistory->createdBy)->toBeInstanceOf(User::class);
    expect($orderHistory->createdBy->id)->toEqual($this->employee->id);
});
it('checks if tracking status changes', function () {
    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::Delivered->value,
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory->field_changed)->toEqual('status');
    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderStatus::Delivered->value);
});
it('checks if tracking priority changes', function () {
    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::LOW->value,
        'new_value' => OrderPriority::URGENT->value,
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory->field_changed)->toEqual('priority');
    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::LOW->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::URGENT->value);
});
it('checks if nullable fields', function () {
    $orderHistory = OrderHistory::create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $this->employee->id,
        // comment is nullable
    ]);

    expect($orderHistory->comment)->toBeNull();
});
it('checks if order history with attachments', function () {
    Storage::fake('public');

    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
    ]);

    // Test attaching a file
    $file = UploadedFile::fake()->create('change-log.pdf', 100, 'application/pdf');
    $attachment = $orderHistory->attach($file, $this->employee->id);

    expect($orderHistory->attachments)->toHaveCount(1);
    expect($attachment->file_name)->toEqual('change-log.pdf');
    expect($attachment->attachable_type)->toEqual('order_history');
    expect($orderHistory->hasAttachments())->toBeTrue();
});
it('checks if tracking multiple status changes', function () {
    // Create multiple history entries for status changes
    $statusChanges = [
        ['from' => OrderStatus::Open->value, 'to' => OrderStatus::InProgress->value],
        ['from' => OrderStatus::InProgress->value, 'to' => OrderStatus::Delivered->value],
    ];

    foreach ($statusChanges as $change) {
        OrderHistory::create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => $change['from'],
            'new_value' => $change['to'],
            'comment' => "Status changed from {$change['from']} to {$change['to']}",
            'created_by' => $this->employee->id,
        ]);
    }

    $histories = OrderHistory::where('order_id', $this->order->id)
        ->orderBy('created_at')
        ->get();

    expect($histories)->toHaveCount(2);
    expect($histories[0]->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($histories[0]->getRawOriginal('new_value'))->toEqual(OrderStatus::InProgress->value);
    expect($histories[1]->getRawOriginal('old_value'))->toEqual(OrderStatus::InProgress->value);
    expect($histories[1]->getRawOriginal('new_value'))->toEqual(OrderStatus::Delivered->value);
});
it('checks if tracking priority changes with comment', function () {
    $orderHistory = OrderHistory::create([
        'order_id' => $this->order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::URGENT->value,
        'comment' => 'Priority escalated due to customer request - Customer called and requested urgent processing',
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::NORMAL->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::URGENT->value);
    expect($orderHistory->comment)->not->toBeNull();
});
it('checks if order history chronological ordering', function () {
    // Create history entries with different timestamps
    $history1 = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
        'created_at' => now()->subDays(2),
    ]);

    $history2 = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
        'created_at' => now()->subDay(),
    ]);

    $history3 = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
        'created_at' => now(),
    ]);

    $histories = OrderHistory::where('order_id', $this->order->id)
        ->orderBy('created_at', 'desc')
        ->pluck('id')
        ->toArray();

    expect($histories)->toEqual([$history3->id, $history2->id, $history1->id]);
});
it('checks if order history with different users', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);

    // Create history entries by different users
    $history1 = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
        'comment' => 'Initial assignment',
    ]);

    $history2 = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $admin->id,
        'comment' => 'Admin review',
    ]);

    $history3 = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $employee2->id,
        'comment' => 'Reassigned to another employee',
    ]);

    // Verify different users created entries
    expect($history1->createdBy->id)->toEqual($this->employee->id);
    expect($history2->createdBy->id)->toEqual($admin->id);
    expect($history3->createdBy->id)->toEqual($employee2->id);
});
it('checks if filtering order history by field changes', function () {
    // Create mixed history entries
    OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'created_by' => $this->employee->id,
    ]);

    OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::HIGH->value,
        'created_by' => $this->employee->id,
    ]);

    OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::InProgress->value,
        'new_value' => OrderStatus::Delivered->value,
        'created_by' => $this->employee->id,
    ]);

    // Filter only status changes
    $statusChanges = OrderHistory::where('order_id', $this->order->id)
        ->where('field_changed', 'status')
        ->get();

    expect($statusChanges)->toHaveCount(2);
    foreach ($statusChanges as $change) {
        expect($change->field_changed)->toEqual('status');
    }
});
it('checks if uuid exists', function () {
    $orderHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory->uuid)->toBeString();
    expect(mb_strlen($orderHistory->uuid))->toEqual(36);

    // UUID length with hyphens
    // Laravel uses UUID v7 (ordered UUIDs) by default
    expect($orderHistory->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
it('checks if mass assignment protection', function () {
    $data = [
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Test comment',
        'created_by' => $this->employee->id,
        'created_at' => now()->subDays(10), // Should be ignored
        'updated_at' => now()->subDays(10), // Should be ignored
    ];

    $orderHistory = OrderHistory::create($data);

    // Verify fillable fields were set
    expect($orderHistory->order_id)->toEqual($data['order_id']);
    expect($orderHistory->comment)->toEqual($data['comment']);

    // Verify timestamps were not overridden
    $this->assertNotEquals($data['created_at'], $orderHistory->created_at);
    $this->assertNotEquals($data['updated_at'], $orderHistory->updated_at);
});
it('checks if order history factory', function () {
    $orderHistory = OrderHistory::factory()->create();

    expect($orderHistory)->toBeInstanceOf(OrderHistory::class);
    expect($orderHistory->order_id)->not->toBeNull();
    expect($orderHistory->created_by)->not->toBeNull();
    expect($orderHistory->field_changed)->not->toBeNull();

    // Test factory with specific attributes
    $specificHistory = OrderHistory::factory()->create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Custom comment',
    ]);

    expect($specificHistory->order_id)->toEqual($this->order->id);
    expect($specificHistory->field_changed)->toEqual('status');
    expect($specificHistory->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($specificHistory->getRawOriginal('new_value'))->toEqual(OrderStatus::InProgress->value);
    expect($specificHistory->comment)->toEqual('Custom comment');
});
it('checks if order history with only status change', function () {
    $orderHistory = OrderHistory::create([
        'order_id' => $this->order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Status change only',
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory->field_changed)->toEqual('status');
    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderStatus::Open->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderStatus::InProgress->value);
});
it('checks if order history with only priority change', function () {
    $orderHistory = OrderHistory::create([
        'order_id' => $this->order->id,
        'field_changed' => 'priority',
        'old_value' => OrderPriority::NORMAL->value,
        'new_value' => OrderPriority::HIGH->value,
        'comment' => 'Priority change only',
        'created_by' => $this->employee->id,
    ]);

    expect($orderHistory->field_changed)->toEqual('priority');
    expect($orderHistory->getRawOriginal('old_value'))->toEqual(OrderPriority::NORMAL->value);
    expect($orderHistory->getRawOriginal('new_value'))->toEqual(OrderPriority::HIGH->value);
});
