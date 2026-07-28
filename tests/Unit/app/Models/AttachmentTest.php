<?php

declare(strict_types=1);

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
it('checks if can create attachment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $attachment = $order->attachments()->create(Attachment::factory()->raw([
        'uploaded_by' => $user->id,
    ]));

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->uploaded_by)->toEqual($user->id);
});
it('checks if polymorphic relationship with order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $attachment = $order->attachments()->create(Attachment::factory()->raw([
        'uploaded_by' => $user->id,
    ]));

    // Test attachable relationship
    expect($attachment->attachable)->toBeInstanceOf(Order::class);
    expect($attachment->attachable->id)->toEqual($order->id);

    // Test inverse relationship
    expect($order->fresh()->attachments->contains($attachment))->toBeTrue();
});
it('checks if polymorphic relationship with order history', function () {
    $user = User::factory()->create();
    $orderHistory = OrderHistory::factory()->create();

    $attachment = $orderHistory->attachments()->create(Attachment::factory()->raw([
        'uploaded_by' => $user->id,
    ]));

    // Test attachable relationship
    expect($attachment->attachable)->toBeInstanceOf(OrderHistory::class);
    expect($attachment->attachable->id)->toEqual($orderHistory->id);

    // Test inverse relationship
    expect($orderHistory->fresh()->attachments->contains($attachment))->toBeTrue();
});
it('checks if uploaded by relationship', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $attachment = $order->attachments()->create(Attachment::factory()->raw([
        'uploaded_by' => $user->id,
    ]));

    expect($attachment->uploadedBy)->toBeInstanceOf(User::class);
    expect($attachment->uploadedBy->id)->toEqual($user->id);
});
it('checks if get url method', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $attachment = $order->attachments()->create(Attachment::factory()->raw([
        'file_path' => 'attachments/Order/' . $order->id . '/test.pdf',
        'uploaded_by' => $user->id,
    ]));

    $expectedUrl = Storage::disk('public')->url('attachments/Order/' . $order->id . '/test.pdf');
    expect($attachment->getUrl())->toEqual($expectedUrl);
});
it('checks if get human readable size method', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $testCases = [
        ['size' => 512, 'expected' => '512 B'],
        ['size' => 1024, 'expected' => '1 KB'],
        ['size' => 1536, 'expected' => '1.5 KB'],
        ['size' => 1048576, 'expected' => '1 MB'],
        ['size' => 1572864, 'expected' => '1.5 MB'],
        ['size' => 1073741824, 'expected' => '1 GB'],
    ];

    foreach ($testCases as $testCase) {
        $attachment = $order->attachments()->create(Attachment::factory()->raw([
            'file_size' => $testCase['size'],
            'uploaded_by' => $user->id,
        ]));

        expect($attachment->getHumanReadableSize())->toEqual($testCase['expected']);
    }
});
it('checks if is image method', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $imageAttachment = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'image/jpeg',
        'uploaded_by' => $user->id,
    ]));

    $documentAttachment = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]));

    expect($imageAttachment->isImage())->toBeTrue();
    expect($documentAttachment->isImage())->toBeFalse();
});
it('checks if is document method', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $wordDoc = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by' => $user->id,
    ]));

    $excelDoc = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'uploaded_by' => $user->id,
    ]));

    $imageFile = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'image/jpeg',
        'uploaded_by' => $user->id,
    ]));

    expect($wordDoc->isDocument())->toBeTrue();
    expect($excelDoc->isDocument())->toBeTrue();
    expect($imageFile->isDocument())->toBeFalse();
});
it('checks if is pdf method', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $pdfAttachment = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]));

    $wordAttachment = $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by' => $user->id,
    ]));

    expect($pdfAttachment->isPdf())->toBeTrue();
    expect($wordAttachment->isPdf())->toBeFalse();
});
it('checks if get extension method', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $testCases = [
        ['filename' => 'document.pdf', 'expected' => 'pdf'],
        ['filename' => 'image.jpg', 'expected' => 'jpg'],
        ['filename' => 'archive.tar.gz', 'expected' => 'gz'],
        ['filename' => 'noextension', 'expected' => ''],
    ];

    foreach ($testCases as $testCase) {
        $attachment = $order->attachments()->create(Attachment::factory()->raw([
            'file_name' => $testCase['filename'],
            'uploaded_by' => $user->id,
        ]));

        expect($attachment->getExtension())->toEqual($testCase['expected']);
    }
});
it('checks if images scope', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create image attachments
    $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'image/jpeg',
        'uploaded_by' => $user->id,
    ]));

    $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'image/png',
        'uploaded_by' => $user->id,
    ]));

    // Create non-image attachment
    $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]));

    $imageAttachments = Attachment::images()->get();

    expect($imageAttachments)->toHaveCount(2);
    foreach ($imageAttachments as $attachment) {
        expect($attachment->mime_type)->toStartWith('image/');
    }
});
it('checks if documents scope', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create document attachments
    $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]));

    $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'uploaded_by' => $user->id,
    ]));

    // Create image attachment
    $order->attachments()->create(Attachment::factory()->raw([
        'mime_type' => 'image/jpeg',
        'uploaded_by' => $user->id,
    ]));

    $documentAttachments = Attachment::documents()->get();

    expect($documentAttachments)->toHaveCount(2);
    foreach ($documentAttachments as $attachment) {
        expect($attachment->mime_type)->toStartWith('application/');
    }
});
it('checks if delete removes file and record', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create a real file in the fake storage
    $file = UploadedFile::fake()->image('photo.jpg');
    $filePath = $file->store('attachments/Order/' . $order->id, 'public');

    $attachment = $order->attachments()->create(Attachment::factory()->raw([
        'file_path' => $filePath,
        'file_size' => $file->getSize(),
        'mime_type' => 'image/jpeg',
        'uploaded_by' => $user->id,
    ]));

    // Verify file exists
    Storage::disk('public')->assertExists($filePath);

    // Delete attachment
    $attachment->delete();

    // Verify file is deleted
    Storage::disk('public')->assertMissing($filePath);

    // Verify record is deleted
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});
it('checks if delete handles missing physical file', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $attachment = $order->attachments()->create(Attachment::factory()->raw([
        'file_path' => 'attachments/non-existent.pdf',
        'uploaded_by' => $user->id,
    ]));

    // The file does not exist in the storage
    Storage::disk('public')->assertMissing($attachment->file_path);

    // Delete should not throw an exception
    $result = $attachment->delete();

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});
it('checks if mime type constants', function () {
    expect(Attachment::MIME_PDF)->toEqual('application/pdf');
    expect(Attachment::MIME_JPG)->toEqual('image/jpeg');
    expect(Attachment::MIME_PNG)->toEqual('image/png');
    expect(Attachment::MIME_DOCX)->toEqual('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});
it('checks if mime type groups', function () {
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/jpeg');
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/png');

    expect(Attachment::DOCUMENT_MIME_TYPES)->toContain('application/pdf');
    expect(Attachment::DOCUMENT_MIME_TYPES)->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect(Attachment::SPREADSHEET_MIME_TYPES)->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(Attachment::SPREADSHEET_MIME_TYPES)->toContain('text/csv');
});
