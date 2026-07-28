<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});
it('checks if file type detection', function () {
    $testCases = [
        // Images
        ['file' => UploadedFile::fake()->image('photo.jpg'), 'expectedType' => 'image', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],
        ['file' => UploadedFile::fake()->image('photo.png'), 'expectedType' => 'image', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],
        ['file' => UploadedFile::fake()->image('photo.gif'), 'expectedType' => 'image', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],

        // Documents
        ['file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'), 'expectedType' => 'document', 'isImage' => false, 'isDocument' => false, 'isPdf' => true],
        ['file' => UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), 'expectedType' => 'document', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
        ['file' => UploadedFile::fake()->create('spreadsheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), 'expectedType' => 'spreadsheet', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
    ];

    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    foreach ($testCases as $testCase) {
        $attachment = $order->attach($testCase['file'], $user->id);

        expect($attachment->isImage())->toEqual($testCase['isImage'], "Failed for file: {$testCase['file']->getClientOriginalName()}");
        expect($attachment->isDocument())->toEqual($testCase['isDocument'], "Failed for file: {$testCase['file']->getClientOriginalName()}");
        expect($attachment->isPdf())->toEqual($testCase['isPdf'], "Failed for file: {$testCase['file']->getClientOriginalName()}");
    }
});
it('checks if file size validation', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create files of different sizes
    $smallFile = UploadedFile::fake()->create('small.pdf', 100);
    // 100 KB
    $mediumFile = UploadedFile::fake()->create('medium.pdf', 5120);
    // 5 MB
    $largeFile = UploadedFile::fake()->create('large.pdf', 10240);

    // 10 MB
    // All should be attachable (no built-in size limit in the model)
    $smallAttachment = $order->attach($smallFile, $user->id);
    $mediumAttachment = $order->attach($mediumFile, $user->id);
    $largeAttachment = $order->attach($largeFile, $user->id);

    expect($smallAttachment->file_size)->toEqual(100 * 1024);
    expect($mediumAttachment->file_size)->toEqual(5120 * 1024);
    expect($largeAttachment->file_size)->toEqual(10240 * 1024);
});
it('checks if mime type constants', function () {
    // Test individual MIME type constants
    expect(Attachment::MIME_PDF)->toEqual('application/pdf');
    expect(Attachment::MIME_DOC)->toEqual('application/msword');
    expect(Attachment::MIME_DOCX)->toEqual('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect(Attachment::MIME_JPG)->toEqual('image/jpeg');
    expect(Attachment::MIME_PNG)->toEqual('image/png');
    expect(Attachment::MIME_CSV)->toEqual('text/csv');
    expect(Attachment::MIME_ZIP)->toEqual('application/zip');
});
it('checks if mime type groups', function () {
    // Test IMAGE_MIME_TYPES
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/jpeg');
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/png');
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/gif');
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/svg+xml');
    expect(Attachment::IMAGE_MIME_TYPES)->toContain('image/webp');
    expect(Attachment::IMAGE_MIME_TYPES)->toHaveCount(5);

    // Test DOCUMENT_MIME_TYPES
    expect(Attachment::DOCUMENT_MIME_TYPES)->toContain('application/msword');
    expect(Attachment::DOCUMENT_MIME_TYPES)->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect(Attachment::DOCUMENT_MIME_TYPES)->toContain('application/pdf');
    expect(Attachment::DOCUMENT_MIME_TYPES)->toContain('text/plain');

    // Test SPREADSHEET_MIME_TYPES
    expect(Attachment::SPREADSHEET_MIME_TYPES)->toContain('application/vnd.ms-excel');
    expect(Attachment::SPREADSHEET_MIME_TYPES)->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(Attachment::SPREADSHEET_MIME_TYPES)->toContain('text/csv');

    // Test PRESENTATION_MIME_TYPES
    expect(Attachment::PRESENTATION_MIME_TYPES)->toContain('application/vnd.ms-powerpoint');
    expect(Attachment::PRESENTATION_MIME_TYPES)->toContain('application/vnd.openxmlformats-officedocument.presentationml.presentation');

    // Test ARCHIVE_MIME_TYPES
    expect(Attachment::ARCHIVE_MIME_TYPES)->toContain('application/zip');
    expect(Attachment::ARCHIVE_MIME_TYPES)->toContain('application/x-rar-compressed');
    expect(Attachment::ARCHIVE_MIME_TYPES)->toContain('application/x-7z-compressed');
});
it('checks if file extension extraction', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();
    $testCases = [
        ['filename' => 'document.pdf', 'expected' => 'pdf'],
        ['filename' => 'image.jpg', 'expected' => 'jpg'],
        ['filename' => 'archive.tar.gz', 'expected' => 'gz'],
        ['filename' => 'script.min.js', 'expected' => 'js'],
        ['filename' => 'no_extension', 'expected' => ''],
        ['filename' => '.hidden', 'expected' => 'hidden'],
    ];

    foreach ($testCases as $testCase) {
        $attachment = Attachment::create([
            'attachable_type' => Order::class,
            'attachable_id' => $order->id,
            'file_name' => $testCase['filename'],
            'file_path' => 'attachments/' . $testCase['filename'],
            'file_size' => 1024,
            'mime_type' => 'application/octet-stream',
            'uploaded_by' => $user->id,
        ]);

        expect($attachment->getExtension())->toEqual($testCase['expected'], "Failed for filename: {$testCase['filename']}");
    }
});
it('checks if mime type storage', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    $files = [
        ['file' => UploadedFile::fake()->image('photo.jpg'), 'expectedMime' => 'image/jpeg'],
        ['file' => UploadedFile::fake()->image('photo.png'), 'expectedMime' => 'image/png'],
        ['file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'), 'expectedMime' => 'application/pdf'],
        ['file' => UploadedFile::fake()->create('data.json', 50, 'application/json'), 'expectedMime' => 'application/json'],
        ['file' => UploadedFile::fake()->create('data.xml', 50, 'application/xml'), 'expectedMime' => 'application/xml'],
    ];

    foreach ($files as $fileData) {
        $attachment = $order->attach($fileData['file'], $user->id);

        expect($attachment->mime_type)->toEqual($fileData['expectedMime'], "MIME type mismatch for file: {$fileData['file']->getClientOriginalName()}");
    }
});
it('checks if file name handling', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Test files with special characters in names
    $specialCharFiles = [
        'file with spaces.pdf',
        'file_with_underscores.pdf',
        'file-with-dashes.pdf',
        'file.multiple.dots.pdf',
        'ñandú_español.pdf', // Unicode characters
        'file@special#chars.pdf',
    ];

    foreach ($specialCharFiles as $filename) {
        $file = UploadedFile::fake()->create($filename, 100, 'application/pdf');
        $attachment = $order->attach($file, $user->id);

        // Original filename should be preserved
        expect($attachment->file_name)->toEqual($filename);

        // File should be stored successfully
        Storage::disk('public')->assertExists($attachment->file_path);
    }
});
it('checks if empty file handling', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();

    // Create a zero-byte file
    $emptyFile = UploadedFile::fake()->create('empty.txt', 0, 'text/plain');

    $attachment = $order->attach($emptyFile, $user->id);

    expect($attachment->file_size)->toEqual(0);
    expect($attachment->file_name)->toEqual('empty.txt');
    expect($attachment->mime_type)->toEqual('text/plain');
});
it('checks if comprehensive file type detection', function () {
    $user = User::factory()->create();
    $order = Order::factory()->createQuietly();
    $fileTypeTests = [
        // Images
        ['mime' => 'image/jpeg', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'image/png', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'image/gif', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'image/svg+xml', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'image/webp', 'isImage' => true, 'isDocument' => false, 'isPdf' => false],

        // Documents
        ['mime' => 'application/pdf', 'isImage' => false, 'isDocument' => false, 'isPdf' => true],
        ['mime' => 'application/msword', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
        ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
        ['mime' => 'text/plain', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
        ['mime' => 'application/rtf', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],

        // Spreadsheets
        ['mime' => 'application/vnd.ms-excel', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
        ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],

        // Presentations
        ['mime' => 'application/vnd.ms-powerpoint', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],
        ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'isImage' => false, 'isDocument' => true, 'isPdf' => false],

        // Others
        ['mime' => 'application/zip', 'isImage' => false, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'application/json', 'isImage' => false, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'video/mp4', 'isImage' => false, 'isDocument' => false, 'isPdf' => false],
        ['mime' => 'audio/mpeg', 'isImage' => false, 'isDocument' => false, 'isPdf' => false],
    ];

    foreach ($fileTypeTests as $test) {
        $attachment = Attachment::create([
            'attachable_type' => Order::class,
            'attachable_id' => $order->id,
            'file_name' => 'test_file',
            'file_path' => 'attachments/test_file',
            'file_size' => 1024,
            'mime_type' => $test['mime'],
            'uploaded_by' => $user->id,
        ]);

        expect($attachment->isImage())->toEqual($test['isImage'], "isImage() failed for MIME type: {$test['mime']}");
        expect($attachment->isDocument())->toEqual($test['isDocument'], "isDocument() failed for MIME type: {$test['mime']}");
        expect($attachment->isPdf())->toEqual($test['isPdf'], "isPdf() failed for MIME type: {$test['mime']}");
    }
});
