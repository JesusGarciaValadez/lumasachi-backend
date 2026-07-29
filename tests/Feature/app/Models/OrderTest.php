<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});
it('checks order creation with relationships', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $assignee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
        'assigned_to' => $assignee->id,
    ]);

    expect($order->customer)->toBeInstanceOf(User::class);
    expect($order->customer->id)->toEqual($customer->id);

    expect($order->createdBy)->toBeInstanceOf(User::class);
    expect($order->createdBy->id)->toEqual($creator->id);

    expect($order->updatedBy)->toBeInstanceOf(User::class);
    expect($order->updatedBy->id)->toEqual($updater->id);

    expect($order->assignedTo)->toBeInstanceOf(User::class);
    expect($order->assignedTo->id)->toEqual($assignee->id);
});
it('checks order status transitions', function () {
    $order = Order::factory()->createQuietly(['status' => OrderStatus::Open->value]);
    $order->update(['status' => OrderStatus::InProgress->value]);

    expect($order->status->value)->toEqual(OrderStatus::InProgress->value);

    $order->update(['status' => OrderStatus::Delivered->value]);

    expect($order->status->value)->toEqual(OrderStatus::Delivered->value);
});
it('checks order attachments', function () {
    $order = Order::factory()->createQuietly();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $attachment = $order->attach($file, $order->created_by);

    expect($order->attachments)->toHaveCount(1);
    expect($attachment->file_name)->toEqual($file->getClientOriginalName());
    expect($attachment->file_name)->toEqual('document.pdf');
});
it('checks order multiple attachments', function () {
    $order = Order::factory()->createQuietly();

    $files = [
        UploadedFile::fake()->create('document1.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('document2.pdf', 200, 'application/pdf'),
    ];

    foreach ($files as $file) {
        $order->attach($file, $order->created_by);
    }

    expect($order->attachments)->toHaveCount(2);
});
it('checks order history records', function () {
    $order = Order::factory()->createQuietly();

    $history = OrderHistory::factory()->count(3)->state(new Sequence(
        ['order_id' => $order->id]
    ))->create();

    expect($order->orderHistories)->toHaveCount(3);
});
it('checks customer relationship only returns customer role users', function () {
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

    // Create order with employee ID as customer_id (shouldn't work with relationship)
    $order = Order::factory()->createQuietly([
        'customer_id' => $employee->id,
        'assigned_to' => User::factory()->create()->id,
    ]);

    // The customer relationship should return null because employee is not a customer
    expect($order->customer)->toBeNull();

    // Create order with actual customer
    $order2 = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'assigned_to' => User::factory()->create()->id,
    ]);
    expect($order2->customer)->not->toBeNull();
    expect($order2->customer->id)->toEqual($customer->id);
});
it('checks order date casting', function () {
    $estimatedDate = now()->addDays(7);
    $completedDate = now()->subDays(2);

    $order = Order::factory()->createQuietly([
        'estimated_completion' => $estimatedDate,
        'actual_completion' => $completedDate,
    ]);

    // Test that dates are cast to Carbon instances
    expect($order->estimated_completion)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    expect($order->actual_completion)->toBeInstanceOf(Carbon\CarbonImmutable::class);

    // Test date values
    expect($order->estimated_completion->format('Y-m-d'))->toEqual($estimatedDate->format('Y-m-d'));
    expect($order->actual_completion->format('Y-m-d'))->toEqual($completedDate->format('Y-m-d'));
});
it('checks order factory states', function () {
    // Test completed state
    $completedOrder = Order::factory()->completed()->createQuietly();
    expect($completedOrder->status->value)->toEqual(OrderStatus::Delivered->value);
    expect($completedOrder->actual_completion)->not->toBeNull();

    // Test open state
    $openOrder = Order::factory()->open()->createQuietly();
    expect($openOrder->status->value)->toEqual(OrderStatus::Open->value);
    expect($openOrder->actual_completion)->toBeNull();
});
it('checks has attachments trait methods', function () {
    $order = Order::factory()->createQuietly();
    $user = $order->createdBy;

    // Test hasAttachments method
    expect($order->hasAttachments())->toBeFalse();

    // Add attachments
    $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $imageFile = UploadedFile::fake()->create('image.jpg', 200, 'image/jpeg');

    $order->attach($pdfFile, $user->id);
    $order->attach($imageFile, $user->id);

    // Test hasAttachments after adding files
    expect($order->hasAttachments())->toBeTrue();

    // Test getAttachmentsByType
    $pdfAttachments = $order->getAttachmentsByType('application/pdf');
    expect($pdfAttachments)->toHaveCount(1);

    // Test partial mime type
    $imageAttachments = $order->getAttachmentsByType('image');
    expect($imageAttachments)->toHaveCount(1);

    // Test getImageAttachments
    $images = $order->getImageAttachments();
    expect($images)->toHaveCount(1);

    // Test getDocumentAttachments
    $documents = $order->getDocumentAttachments();
    expect($documents)->toHaveCount(1);

    // Test getTotalAttachmentsSize
    $totalSize = $order->getTotalAttachmentsSize();
    expect($totalSize)->toEqual(300 * 1024);

    // 300 KB (100 + 200 KB)
    // Test getTotalAttachmentsSizeFormatted
    $formattedSize = $order->getTotalAttachmentsSizeFormatted();
    expect($formattedSize)->toEqual('300 KB');
});
it('checks detaching attachments', function () {
    $order = Order::factory()->createQuietly();
    $user = $order->createdBy;

    // Add attachments
    $file1 = UploadedFile::fake()->create('file1.pdf', 100);
    $file2 = UploadedFile::fake()->create('file2.pdf', 200);

    $attachment1 = $order->attach($file1, $user->id);
    $attachment2 = $order->attach($file2, $user->id);

    expect($order->attachments)->toHaveCount(2);

    // Test detaching single attachment
    $result = $order->detach($attachment1->id);
    expect($result)->toBeTrue();
    expect($order->fresh()->attachments)->toHaveCount(1);

    // Test detaching non-existent attachment
    $result = $order->detach('00');
    expect($result)->toBeFalse();

    // Test detachAll
    $order->attach($file1, $user->id);
    // Add another attachment
    $order = $order->fresh();
    // Refresh to get all attachments
    expect($order->attachments)->toHaveCount(2);

    $deletedCount = $order->detachAll();
    expect($deletedCount)->toEqual(2);
    expect($order->fresh()->attachments)->toHaveCount(0);
});
it('checks order uses uuid as primary key', function () {
    $order = Order::factory()->createQuietly();

    // Check that ID is a valid UUID (Laravel 11 uses UUID v7)
    expect($order->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
it('checks mass assignment protection', function () {
    $fillableFields = [
        'customer_id',
        'title',
        'description',
        'status',
        'lifecycle_status',
        'disposition_status',
        'priority',
        'estimated_completion',
        'actual_completion',
        'notes',
        'created_by',
        'updated_by',
        'assigned_to',
    ];

    $order = new Order();
    expect($order->getFillable())->toEqual($fillableFields);
});
it('checks order priority values', function () {
    $priorities = [
        OrderPriority::LOW->value,
        OrderPriority::NORMAL->value,
        OrderPriority::HIGH->value,
        OrderPriority::URGENT->value,
    ];

    foreach ($priorities as $priority) {
        $order = Order::factory()->createQuietly(['priority' => $priority]);
        expect($order->priority->value)->toEqual($priority);
    }
});
it('checks order status values', function () {
    $statuses = [
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

    foreach ($statuses as $status) {
        $order = Order::factory()->createQuietly(['status' => $status]);
        expect($order->status->value)->toEqual($status);
    }
});
it('checks order with null optional fields', function () {
    $order = Order::factory()->createQuietly([
        'actual_completion' => null,
        'notes' => null,
        'assigned_to' => User::factory()->create()->id,
    ]);

    expect($order->actual_completion)->toBeNull();
    expect($order->notes)->toBeNull();
    expect($order->assigned_to)->not->toBeNull();
    expect($order->assignedTo)->not->toBeNull();
});
