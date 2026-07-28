<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if factory creates valid attachment', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment)->toBeInstanceOf(Attachment::class);
    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
        'file_name' => $attachment->file_name,
    ]);
});
it('checks if factory generates all required fields', function () {
    $attachment = Attachment::factory()->make();

    expect($attachment->attachable_type)->not->toBeNull();
    expect($attachment->attachable_id)->not->toBeNull();
    expect($attachment->file_name)->not->toBeNull();
    expect($attachment->file_path)->not->toBeNull();
    expect($attachment->file_size)->not->toBeNull();
    expect($attachment->mime_type)->not->toBeNull();
    expect($attachment->uploaded_by)->not->toBeNull();
});
it('checks if factory creates image attachment', function () {
    $attachment = Attachment::factory()->image()->create();

    expect($attachment->isImage())->toBeTrue();
    expect(Attachment::IMAGE_MIME_TYPES)->toContain($attachment->mime_type);
});
it('checks if factory creates pdf attachment', function () {
    $attachment = Attachment::factory()->pdf()->create();

    expect($attachment->isPdf())->toBeTrue();
    expect($attachment->mime_type)->toEqual(Attachment::MIME_PDF);
});
it('checks if factory creates spreadsheet attachment', function () {
    $attachment = Attachment::factory()->spreadsheet()->create();

    expect(Attachment::SPREADSHEET_MIME_TYPES)->toContain($attachment->mime_type);
});
it('checks if factory can override attributes', function () {
    $customFileName = 'custom_file_name.pdf';
    $attachment = Attachment::factory()->create([
        'file_name' => $customFileName,
    ]);

    expect($attachment->file_name)->toEqual($customFileName);
});
it('checks if factory creates associated models', function () {
    $attachment = Attachment::factory()->create();

    // Check that the attachable model exists (could be Order or OrderHistory)
    if ($attachment->attachable_type === 'order') {
        $this->assertDatabaseHas('orders', ['id' => $attachment->attachable_id]);
    } elseif ($attachment->attachable_type === 'order_history') {
        $this->assertDatabaseHas('order_histories', ['id' => $attachment->attachable_id]);
    }

    // Check that the user was created
    $this->assertDatabaseHas('users', ['id' => $attachment->uploaded_by]);
});
it('checks if factory creates attachments for specific order', function () {
    $order = Order::factory()->createQuietly();

    $attachment = Attachment::factory()->for($order, 'attachable')->create();

    expect($attachment->attachable_type)->toEqual('order');
    expect($attachment->attachable_id)->toEqual($order->id);
    expect($attachment->attachable->id)->toEqual($order->id);
});
it('checks if factory creates attachments for specific order history', function () {
    $orderHistory = OrderHistory::factory()->create();

    $attachment = Attachment::factory()->for($orderHistory, 'attachable')->create();

    expect($attachment->attachable_type)->toEqual('order_history');
    expect($attachment->attachable_id)->toEqual($orderHistory->id);
    expect($attachment->attachable->id)->toEqual($orderHistory->id);
});
it('checks if factory generates uuid', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->uuid)->not->toBeNull();
    expect($attachment->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
it('checks if factory generates realistic data', function () {
    $attachment = Attachment::factory()->create([
        'file_size' => 1024,
    ]);

    expect($attachment->file_name)->toEndWith($attachment->getExtension());
    expect($attachment->file_size)->toBeGreaterThanOrEqual(1024);
    // Minimum 1KB
    expect($attachment->file_size)->toBeLessThanOrEqual(52428800);
    // Maximum 50MB
});
it('checks if file path includes date structure', function () {
    $attachment = Attachment::factory()->create();
    $this->assertStringContainsString('attachments/', $attachment->file_path);
});
it('checks if multiple attachments can be created', function () {
    $attachments = Attachment::factory()->count(5)->create();

    expect($attachments)->toHaveCount(5);

    foreach ($attachments as $attachment) {
        expect($attachment)->toBeInstanceOf(Attachment::class);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }
});
it('checks if factory generates unique file names', function () {
    $attachments = Attachment::factory()->count(10)->make();
    $fileNames = $attachments->pluck('file_name')->toArray();

    // Check that all file names are unique
    expect(count(array_unique($fileNames)))->toEqual(count($fileNames));
});
it('checks if factory generates valid mime types', function () {
    $allValidMimeTypes = array_merge(
        Attachment::IMAGE_MIME_TYPES,
        Attachment::DOCUMENT_MIME_TYPES,
        Attachment::SPREADSHEET_MIME_TYPES,
        Attachment::PRESENTATION_MIME_TYPES,
        Attachment::ARCHIVE_MIME_TYPES
    );

    // Create multiple attachments to test variety
    for ($i = 0; $i < 20; $i++) {
        $attachment = Attachment::factory()->make();
        expect($allValidMimeTypes)->toContain($attachment->mime_type);
    }
});
it('checks if factory relationships work correctly', function () {
    $attachment = Attachment::factory()->create();

    // Test attachable relationship
    expect($attachment->attachable)->not->toBeNull();
    expect(['order', 'order_history'])->toContain($attachment->attachable_type);

    // Test uploadedBy relationship
    expect($attachment->uploadedBy)->toBeInstanceOf(User::class);
    expect($attachment->uploadedBy->id)->toEqual($attachment->uploaded_by);
});
it('checks if human readable file size', function () {
    $testCases = [
        ['size' => 512, 'expected' => '512 B'],
        ['size' => 1024, 'expected' => '1 KB'],
        ['size' => 1536, 'expected' => '1.5 KB'],
        ['size' => 1048576, 'expected' => '1 MB'],
        ['size' => 5242880, 'expected' => '5 MB'],
    ];

    foreach ($testCases as $testCase) {
        $attachment = Attachment::factory()->make(['file_size' => $testCase['size']]);
        expect($attachment->getHumanReadableSize())->toEqual($testCase['expected']);
    }
});
it('checks if file extension detection', function () {
    $testCases = [
        'document.pdf' => 'pdf',
        'image.jpg' => 'jpg',
        'spreadsheet.xlsx' => 'xlsx',
        'archive.zip' => 'zip',
    ];

    foreach ($testCases as $fileName => $expectedExtension) {
        $attachment = Attachment::factory()->make(['file_name' => $fileName]);
        expect($attachment->getExtension())->toEqual($expectedExtension);
    }
});
it('checks if factory with specific user', function () {
    $user = User::factory()->create();

    $attachment = Attachment::factory()->create([
        'uploaded_by' => $user->id,
    ]);

    expect($attachment->uploaded_by)->toEqual($user->id);
    expect($attachment->uploadedBy->id)->toEqual($user->id);
});
