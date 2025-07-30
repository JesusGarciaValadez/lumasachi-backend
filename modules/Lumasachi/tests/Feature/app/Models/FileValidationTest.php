<?php

namespace Modules\Lumasachi\tests\Feature\app\Models;

use Tests\TestCase;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class FileValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test file type validation by MIME type
     */
    public function test_file_type_detection()
    {
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
        $order = Order::factory()->create();

        foreach ($testCases as $testCase) {
            $attachment = $order->attach($testCase['file'], $user->id);

            $this->assertEquals($testCase['isImage'], $attachment->isImage(),
                "Failed for file: {$testCase['file']->getClientOriginalName()}");
            $this->assertEquals($testCase['isDocument'], $attachment->isDocument(),
                "Failed for file: {$testCase['file']->getClientOriginalName()}");
            $this->assertEquals($testCase['isPdf'], $attachment->isPdf(),
                "Failed for file: {$testCase['file']->getClientOriginalName()}");
        }
    }

    /**
     * Test maximum file size validation
     */
    public function test_file_size_validation()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Create files of different sizes
        $smallFile = UploadedFile::fake()->create('small.pdf', 100); // 100 KB
        $mediumFile = UploadedFile::fake()->create('medium.pdf', 5120); // 5 MB
        $largeFile = UploadedFile::fake()->create('large.pdf', 10240); // 10 MB

        // All should be attachable (no built-in size limit in the model)
        $smallAttachment = $order->attach($smallFile, $user->id);
        $mediumAttachment = $order->attach($mediumFile, $user->id);
        $largeAttachment = $order->attach($largeFile, $user->id);

        $this->assertEquals(100 * 1024, $smallAttachment->file_size);
        $this->assertEquals(5120 * 1024, $mediumAttachment->file_size);
        $this->assertEquals(10240 * 1024, $largeAttachment->file_size);
    }

    /**
     * Test MIME type constants are correctly defined
     */
    public function test_mime_type_constants()
    {
        // Test individual MIME type constants
        $this->assertEquals('application/pdf', Attachment::MIME_PDF);
        $this->assertEquals('application/msword', Attachment::MIME_DOC);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', Attachment::MIME_DOCX);
        $this->assertEquals('image/jpeg', Attachment::MIME_JPG);
        $this->assertEquals('image/png', Attachment::MIME_PNG);
        $this->assertEquals('text/csv', Attachment::MIME_CSV);
        $this->assertEquals('application/zip', Attachment::MIME_ZIP);
    }

    /**
     * Test MIME type groups contain correct types
     */
    public function test_mime_type_groups()
    {
        // Test IMAGE_MIME_TYPES
        $this->assertContains('image/jpeg', Attachment::IMAGE_MIME_TYPES);
        $this->assertContains('image/png', Attachment::IMAGE_MIME_TYPES);
        $this->assertContains('image/gif', Attachment::IMAGE_MIME_TYPES);
        $this->assertContains('image/svg+xml', Attachment::IMAGE_MIME_TYPES);
        $this->assertContains('image/webp', Attachment::IMAGE_MIME_TYPES);
        $this->assertCount(5, Attachment::IMAGE_MIME_TYPES);

        // Test DOCUMENT_MIME_TYPES
        $this->assertContains('application/msword', Attachment::DOCUMENT_MIME_TYPES);
        $this->assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', Attachment::DOCUMENT_MIME_TYPES);
        $this->assertContains('application/pdf', Attachment::DOCUMENT_MIME_TYPES);
        $this->assertContains('text/plain', Attachment::DOCUMENT_MIME_TYPES);

        // Test SPREADSHEET_MIME_TYPES
        $this->assertContains('application/vnd.ms-excel', Attachment::SPREADSHEET_MIME_TYPES);
        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Attachment::SPREADSHEET_MIME_TYPES);
        $this->assertContains('text/csv', Attachment::SPREADSHEET_MIME_TYPES);

        // Test PRESENTATION_MIME_TYPES
        $this->assertContains('application/vnd.ms-powerpoint', Attachment::PRESENTATION_MIME_TYPES);
        $this->assertContains('application/vnd.openxmlformats-officedocument.presentationml.presentation', Attachment::PRESENTATION_MIME_TYPES);

        // Test ARCHIVE_MIME_TYPES
        $this->assertContains('application/zip', Attachment::ARCHIVE_MIME_TYPES);
        $this->assertContains('application/x-rar-compressed', Attachment::ARCHIVE_MIME_TYPES);
        $this->assertContains('application/x-7z-compressed', Attachment::ARCHIVE_MIME_TYPES);
    }

    /**
     * Test file extension extraction
     */
    public function test_file_extension_extraction()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
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
            'uploaded_by' => $user->id
        ]);

            $this->assertEquals($testCase['expected'], $attachment->getExtension(),
                "Failed for filename: {$testCase['filename']}");
        }
    }

    /**
     * Test that different file types are correctly stored with their MIME types
     */
    public function test_mime_type_storage()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $files = [
            ['file' => UploadedFile::fake()->image('photo.jpg'), 'expectedMime' => 'image/jpeg'],
            ['file' => UploadedFile::fake()->image('photo.png'), 'expectedMime' => 'image/png'],
            ['file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'), 'expectedMime' => 'application/pdf'],
            ['file' => UploadedFile::fake()->create('data.json', 50, 'application/json'), 'expectedMime' => 'application/json'],
            ['file' => UploadedFile::fake()->create('data.xml', 50, 'application/xml'), 'expectedMime' => 'application/xml'],
        ];

        foreach ($files as $fileData) {
            $attachment = $order->attach($fileData['file'], $user->id);

            $this->assertEquals($fileData['expectedMime'], $attachment->mime_type,
                "MIME type mismatch for file: {$fileData['file']->getClientOriginalName()}");
        }
    }

    /**
     * Test file name sanitization
     */
    public function test_file_name_handling()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

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
            $this->assertEquals($filename, $attachment->file_name);

            // File should be stored successfully
            Storage::disk('public')->assertExists($attachment->file_path);
        }
    }

    /**
     * Test handling of empty or zero-byte files
     */
    public function test_empty_file_handling()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Create a zero-byte file
        $emptyFile = UploadedFile::fake()->create('empty.txt', 0, 'text/plain');

        $attachment = $order->attach($emptyFile, $user->id);

        $this->assertEquals(0, $attachment->file_size);
        $this->assertEquals('empty.txt', $attachment->file_name);
        $this->assertEquals('text/plain', $attachment->mime_type);
    }

    /**
     * Test attachment type detection for various file formats
     */
    public function test_comprehensive_file_type_detection()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
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
                'uploaded_by' => $user->id
            ]);

            $this->assertEquals($test['isImage'], $attachment->isImage(),
                "isImage() failed for MIME type: {$test['mime']}");
            $this->assertEquals($test['isDocument'], $attachment->isDocument(),
                "isDocument() failed for MIME type: {$test['mime']}");
            $this->assertEquals($test['isPdf'], $attachment->isPdf(),
                "isPdf() failed for MIME type: {$test['mime']}");
        }
    }
}
