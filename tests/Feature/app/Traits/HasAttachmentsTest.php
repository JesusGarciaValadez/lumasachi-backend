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

    // Create a test user
    $this->user = User::factory()->create();

    // Create an order instance for testing
    $this->order = Order::factory()->createQuietly(['title' => 'Test Order']);
});
it('checks if attachments relationship', function () {
    $attachment = Attachment::create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'test.pdf',
        'file_path' => 'attachments/test.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->user->id,
    ]);

    $this->order->refresh();
    expect($this->order->attachments)->toHaveCount(1);
    expect($this->order->attachments->contains($attachment))->toBeTrue();
});
it('checks if attach method', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $attachment = $this->order->attach($file, $this->user->id);

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->file_name)->toEqual('photo.jpg');
    expect($attachment->file_size)->toEqual($file->getSize());
    expect($attachment->mime_type)->toEqual('image/jpeg');
    expect($attachment->uploaded_by)->toEqual($this->user->id);

    // Check file path format
    $this->assertStringContainsString('attachments/Order/' . $this->order->id, $attachment->file_path);

    // Verify file was stored
    Storage::disk('public')->assertExists($attachment->file_path);
});
it('checks if attach method with custom disk', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $attachment = $this->order->attach($file, $this->user->id, 'local');

    Storage::disk('local')->assertExists($attachment->file_path);
});
it('checks if detach method', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $attachment = $this->order->attach($file, $this->user->id);

    // Verify attachment exists
    expect($this->order->attachments)->toHaveCount(1);
    Storage::disk('public')->assertExists($attachment->file_path);

    // Detach the attachment
    $result = $this->order->detach($attachment->id);

    expect($result)->toBeTrue();
    expect($this->order->fresh()->attachments)->toHaveCount(0);
    Storage::disk('public')->assertMissing($attachment->file_path);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});
it('checks if detach method with non existent attachment', function () {
    // Use a valid UUID format that doesn't exist
    $result = $this->order->detach('00');

    expect($result)->toBeFalse();
});
it('checks if has attachments method', function () {
    expect($this->order->hasAttachments())->toBeFalse();

    $file = UploadedFile::fake()->image('photo.jpg');
    $this->order->attach($file, $this->user->id);

    expect($this->order->hasAttachments())->toBeTrue();
});
it('checks if get attachments by type method', function () {
    // Create various attachments
    $image = UploadedFile::fake()->image('photo.jpg');
    $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $doc = UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->order->attach($image, $this->user->id);
    $this->order->attach($pdf, $this->user->id);
    $this->order->attach($doc, $this->user->id);

    // Test partial type matching
    $imageAttachments = $this->order->getAttachmentsByType('image');
    expect($imageAttachments)->toHaveCount(1);
    expect($imageAttachments->first()->file_name)->toEqual('photo.jpg');

    // Test exact MIME type matching
    $pdfAttachments = $this->order->getAttachmentsByType('application/pdf');
    expect($pdfAttachments)->toHaveCount(1);
    expect($pdfAttachments->first()->file_name)->toEqual('document.pdf');

    // Test application type matching
    $appAttachments = $this->order->getAttachmentsByType('application');
    expect($appAttachments)->toHaveCount(2);
});
it('checks if get image attachments method', function () {
    $image1 = UploadedFile::fake()->image('photo1.jpg');
    $image2 = UploadedFile::fake()->image('photo2.png');
    $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->order->attach($image1, $this->user->id);
    $this->order->attach($image2, $this->user->id);
    $this->order->attach($pdf, $this->user->id);

    $imageAttachments = $this->order->getImageAttachments();

    expect($imageAttachments)->toHaveCount(2);
    foreach ($imageAttachments as $attachment) {
        expect($attachment->mime_type)->toStartWith('image/');
    }
});
it('checks if get document attachments method', function () {
    $image = UploadedFile::fake()->image('photo.jpg');
    $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $word = UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->order->attach($image, $this->user->id);
    $this->order->attach($pdf, $this->user->id);
    $this->order->attach($word, $this->user->id);

    $documentAttachments = $this->order->getDocumentAttachments();

    expect($documentAttachments)->toHaveCount(2);
    foreach ($documentAttachments as $attachment) {
        expect($attachment->mime_type)->toStartWith('application/');
    }
});
it('checks if get total attachments size method', function () {
    expect($this->order->getTotalAttachmentsSize())->toEqual(0);

    // Create attachments with known sizes
    Attachment::create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'file1.pdf',
        'file_path' => 'attachments/file1.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->user->id,
    ]);

    Attachment::create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'file2.pdf',
        'file_path' => 'attachments/file2.pdf',
        'file_size' => 2048,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->user->id,
    ]);

    $this->order->refresh();
    expect($this->order->getTotalAttachmentsSize())->toEqual(3072);
});
it('checks if get total attachments size formatted method', function () {
    // Test with no attachments
    expect($this->order->getTotalAttachmentsSizeFormatted())->toEqual('0 B');

    // Create attachment with specific size
    Attachment::create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'file.pdf',
        'file_path' => 'attachments/file.pdf',
        'file_size' => 1536, // 1.5 KB
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->user->id,
    ]);

    $this->order->refresh();
    expect($this->order->getTotalAttachmentsSizeFormatted())->toEqual('1.5 KB');

    // Add more attachments to test MB
    Attachment::create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'large_file.pdf',
        'file_path' => 'attachments/large_file.pdf',
        'file_size' => 1048576, // 1 MB
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->user->id,
    ]);

    $this->order->refresh();
    $totalSizeBytes = 1536 + 1048576;
    $expectedSize = round($totalSizeBytes / 1024 / 1024, 2) . ' MB';
    expect($this->order->getTotalAttachmentsSizeFormatted())->toEqual($expectedSize);
});
it('checks if detach all method', function () {
    // Create multiple attachments
    $files = [
        UploadedFile::fake()->image('photo1.jpg'),
        UploadedFile::fake()->image('photo2.jpg'),
        UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ];

    $filePaths = [];
    foreach ($files as $file) {
        $attachment = $this->order->attach($file, $this->user->id);
        $filePaths[] = $attachment->file_path;
    }

    // Verify attachments exist
    expect($this->order->attachments)->toHaveCount(3);
    foreach ($filePaths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    // Detach all
    $count = $this->order->detachAll();

    expect($count)->toEqual(3);
    expect($this->order->fresh()->attachments)->toHaveCount(0);

    // Verify files are deleted
    foreach ($filePaths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});
it('checks if attachment file name uniqueness', function () {
    $file1 = UploadedFile::fake()->image('photo.jpg');
    $file2 = UploadedFile::fake()->image('photo.jpg');

    $attachment1 = $this->order->attach($file1, $this->user->id);
    $attachment2 = $this->order->attach($file2, $this->user->id);

    // Both should have the same original file name
    expect($attachment1->file_name)->toEqual('photo.jpg');
    expect($attachment2->file_name)->toEqual('photo.jpg');

    // But different file paths (due to UUID prefix)
    $this->assertNotEquals($attachment1->file_path, $attachment2->file_path);

    // Both files should exist
    Storage::disk('public')->assertExists($attachment1->file_path);
    Storage::disk('public')->assertExists($attachment2->file_path);
});
it('checks if trait with order model', function () {
    $order = Order::factory()->createQuietly();

    // Order model should have the attachments method
    expect(method_exists($order, 'attachments'))->toBeTrue();
    expect(method_exists($order, 'attach'))->toBeTrue();
    expect(method_exists($order, 'detach'))->toBeTrue();
    expect(method_exists($order, 'hasAttachments'))->toBeTrue();

    // Test attaching a file
    $file = UploadedFile::fake()->image('order-photo.jpg');
    $attachment = $order->attach($file, $this->user->id);

    expect($order->attachments)->toHaveCount(1);
    expect($attachment->attachable_id)->toEqual($order->id);
    expect($attachment->attachable_type)->toEqual('order');
});
it('checks if trait with order history model', function () {
    $order = Order::factory()->createQuietly();
    $orderHistory = OrderHistory::create([
        'order_id' => $order->id,
        'field_changed' => OrderHistory::FIELD_STATUS,
        'old_value' => OrderStatus::Open->value,
        'new_value' => OrderStatus::InProgress->value,
        'comment' => 'Status changed',
        'created_by' => $this->user->id,
    ]);

    // OrderHistory model should have the attachments method
    expect(method_exists($orderHistory, 'attachments'))->toBeTrue();
    expect(method_exists($orderHistory, 'attach'))->toBeTrue();
    expect(method_exists($orderHistory, 'detach'))->toBeTrue();
    expect(method_exists($orderHistory, 'hasAttachments'))->toBeTrue();

    // Test attaching a file
    $file = UploadedFile::fake()->create('history-note.pdf', 200, 'application/pdf');
    $attachment = $orderHistory->attach($file, $this->user->id);

    expect($orderHistory->attachments)->toHaveCount(1);
    expect($attachment->attachable_id)->toEqual($orderHistory->id);
    expect($attachment->attachable_type)->toEqual('order_history');
});
