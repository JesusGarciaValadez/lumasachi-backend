<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});
it('checks complete attachment lifecycle with order', function () {
    // Create users
    $customer = User::factory()->create(['role' => 'Customer']);
    $employee = User::factory()->create(['role' => 'Employee']);

    // Create orders
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $employee->id,
        'updated_by' => $employee->id,
    ]);

    // Attach multiple files
    $files = [
        UploadedFile::fake()->image('order-photo1.jpg', 100, 100),
        UploadedFile::fake()->image('order-photo2.png', 200, 200),
        UploadedFile::fake()->create('order-document.pdf', 500, 'application/pdf'),
        UploadedFile::fake()->create('order-quote.xlsx', 300, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    ];

    $attachments = [];
    foreach ($files as $file) {
        $attachments[] = $order->attach($file, $employee->id);
    }

    // Verify that all files were attached
    expect($order->attachments)->toHaveCount(4);
    expect($order->hasAttachments())->toBeTrue();

    // Verify that the files are categorized correctly
    $imageAttachments = $order->getImageAttachments();
    expect($imageAttachments)->toHaveCount(2);

    // Verify the total size
    $totalSize = $order->getTotalAttachmentsSize();
    expect($totalSize)->toBeGreaterThan(0);

    // Verify the readable size format
    $formattedSize = $order->getTotalAttachmentsSizeFormatted();
    $this->assertStringContainsString(' ', $formattedSize);

    // Must have space between number and unit
    // Verify files by MIME type
    $pdfFiles = $order->getAttachmentsByType('application/pdf');
    expect($pdfFiles)->toHaveCount(1);
    expect($pdfFiles->first()->file_name)->toEqual('order-document.pdf');

    // Delete a specific file
    $pdfAttachment = $pdfFiles->first();
    $pdfPath = $pdfAttachment->file_path;
    Storage::disk('public')->assertExists($pdfPath);

    $result = $order->detach($pdfAttachment->id);
    expect($result)->toBeTrue();
    Storage::disk('public')->assertMissing($pdfPath);

    // Verify that there are 3 files left
    $order->refresh();
    expect($order->attachments)->toHaveCount(3);

    // Delete all remaining files
    $remainingPaths = $order->attachments->pluck('file_path')->toArray();
    $deletedCount = $order->detachAll();

    expect($deletedCount)->toEqual(3);
    expect($order->hasAttachments())->toBeFalse();

    // Verify that all files were deleted
    foreach ($remainingPaths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});
it('checks attachments with order history', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create order history
    $history = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => 'status',
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Started working on the order - Customer approved the design',
        'created_by' => $user->id,
    ]);

    // Attach file to the history
    $file = UploadedFile::fake()->image('approved-design.jpg', 500, 500);
    $attachment = $history->attach($file, $user->id);

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->attachable_type)->toEqual('order_history');
    expect($attachment->attachable_id)->toEqual($history->id);
    expect($history->hasAttachments())->toBeTrue();

    // Verify that the file is correctly stored
    $expectedPath = 'attachments/OrderHistory/' . $history->id;
    $this->assertStringContainsString($expectedPath, $attachment->file_path);
    Storage::disk('public')->assertExists($attachment->file_path);

    // Verify polymorphic relationship
    expect($attachment->attachable)->toBeInstanceOf(OrderHistory::class);
    expect($attachment->attachable->id)->toEqual($history->id);
});
it('checks multiple attachments across order and history', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Attach files to the order
    $orderFiles = [
        UploadedFile::fake()->image('order-initial.jpg'),
        UploadedFile::fake()->create('order-specs.pdf', 200, 'application/pdf'),
    ];

    foreach ($orderFiles as $file) {
        $order->attach($file, $user->id);
    }

    // Create multiple histories with attachments
    $histories = [];
    for ($i = 1; $i <= 3; $i++) {
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => $i === 1 ? OrderStatus::Open->value : OrderStatus::InProgress->value,
            'new_value' => OrderStatus::InProgress->value,
            'comment' => "Update {$i}",
            'created_by' => $user->id,
        ]);

        $historyFile = UploadedFile::fake()->image("history-{$i}.jpg");
        $history->attach($historyFile, $user->id);
        $histories[] = $history;
    }

    // Verify attachments of the order
    expect($order->attachments)->toHaveCount(2);

    // Verify attachments of each history
    foreach ($histories as $history) {
        expect($history->attachments)->toHaveCount(1);
    }

    // Verify total attachments in the database
    $totalAttachments = Attachment::count();
    expect($totalAttachments)->toEqual(5);

    // 2 de orden + 3 de historiales
    // Verify that each attachment is correctly associated
    $orderAttachments = Attachment::where('attachable_type', 'order')
        ->where('attachable_id', $order->id)
        ->get();
    expect($orderAttachments)->toHaveCount(2);

    $historyAttachments = Attachment::where('attachable_type', 'order_history')
        ->whereIn('attachable_id', collect($histories)->pluck('id'))
        ->get();
    expect($historyAttachments)->toHaveCount(3);
});
it('checks edge cases and error handling', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Test 1: Try to delete an attachment that does not belong to the order
    $otherOrder = Order::factory()->createQuietly();
    $file = UploadedFile::fake()->image('other.jpg');
    $otherAttachment = $otherOrder->attach($file, $user->id);

    $result = $order->detach($otherAttachment->id);
    expect($result)->toBeFalse();

    // Test 2: Attach an empty file
    $emptyFile = UploadedFile::fake()->create('empty.txt', 0);
    $emptyAttachment = $order->attach($emptyFile, $user->id);
    expect($emptyAttachment->file_size)->toEqual(0);
    expect($emptyAttachment->getHumanReadableSize())->toEqual('0 B');

    // Test 3: Attach files with the same name
    $file1 = UploadedFile::fake()->image('duplicate.jpg');
    $file2 = UploadedFile::fake()->image('duplicate.jpg');

    $attachment1 = $order->attach($file1, $user->id);
    $attachment2 = $order->attach($file2, $user->id);

    // Both should have the same original name
    expect($attachment2->file_name)->toEqual($attachment1->file_name);

    // But different file paths
    $this->assertNotEquals($attachment1->file_path, $attachment2->file_path);

    // Test 4: Verify that when the order is deleted, the attachments are not automatically deleted
    // (this depends on the implementation, but it's good to verify it)
    $orderId = $order->id;
    $attachmentIds = $order->attachments->pluck('id')->toArray();

    // For now we only verify that the attachments exist
    expect(count($attachmentIds))->toBeGreaterThan(0);

    // If in the future cascade delete is implemented, this test should be updated
});
it('checks performance with multiple attachments', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create 20 attachments
    $attachmentCount = 20;
    for ($i = 1; $i <= $attachmentCount; $i++) {
        $file = UploadedFile::fake()->create("file-{$i}.pdf", rand(100, 1000), 'application/pdf');
        $order->attach($file, $user->id);
    }

    // Verify count
    expect($order->attachments)->toHaveCount($attachmentCount);

    // Verify that the queries are efficient
    expect(memory_get_peak_usage() / 1024 / 1024)->toBeLessThan(1000);

    // Less than 1GB of memory
    // Verify filtering methods
    $pdfAttachments = $order->getAttachmentsByType('application/pdf');
    expect($pdfAttachments)->toHaveCount($attachmentCount);

    // Verify total size calculation
    $totalSize = $order->getTotalAttachmentsSize();
    expect($totalSize)->toBeGreaterThan(100 * 1024 * $attachmentCount);

    // At least 100KB per file
    // Delete all attachments efficiently
    $deletedCount = $order->detachAll();
    expect($deletedCount)->toEqual($attachmentCount);
    expect($order->fresh()->attachments)->toHaveCount(0);
});
it('checks referential integrity', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Attach file
    $file = UploadedFile::fake()->image('test.jpg');
    $attachment = $order->attach($file, $user->id);

    // Verify relationship with user
    expect($attachment->uploaded_by)->toEqual($user->id);
    expect($attachment->uploadedBy->id)->toEqual($user->id);

    // Verify that the attachment knows its parent model
    expect($attachment->attachable)->toBeInstanceOf(Order::class);
    expect($attachment->attachable->id)->toEqual($order->id);

    // Verify inverse relationship
    $foundAttachment = $order->attachments()->find($attachment->id);
    expect($foundAttachment)->not->toBeNull();
    expect($foundAttachment->id)->toEqual($attachment->id);
});
