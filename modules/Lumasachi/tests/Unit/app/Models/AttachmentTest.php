<?php

namespace Modules\Lumasachi\tests\Unit\app\Models;

use Tests\TestCase;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test basic attachment creation
     */
    public function test_can_create_attachment()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'test.pdf',
            'file_path' => 'attachments/Order/' . $order->id . '/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('test.pdf', $attachment->file_name);
        $this->assertEquals(1024, $attachment->file_size);
        $this->assertEquals('application/pdf', $attachment->mime_type);
        $this->assertEquals($user->id, $attachment->uploaded_by);
    }

    /**
     * Test polymorphic relationships with Order
     */
    public function test_polymorphic_relationship_with_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'order_doc.pdf',
            'file_path' => 'attachments/Order/' . $order->id . '/order_doc.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        // Test attachable relationship
        $this->assertInstanceOf(Order::class, $attachment->attachable);
        $this->assertEquals($order->id, $attachment->attachable->id);

        // Test inverse relationship
        $this->assertTrue($order->attachments->contains($attachment));
    }

    /**
     * Test polymorphic relationships with OrderHistory
     */
    public function test_polymorphic_relationship_with_order_history()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'comment' => 'Status changed',
            'created_by' => $user->id
        ]);

        $attachment = Attachment::create([
            'attachable_type' => 'order_history',
            'attachable_id' => $orderHistory->id,
            'file_name' => 'history_doc.pdf',
            'file_path' => 'attachments/OrderHistory/' . $orderHistory->id . '/history_doc.pdf',
            'file_size' => 3072,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        // Test attachable relationship
        $this->assertInstanceOf(OrderHistory::class, $attachment->attachable);
        $this->assertEquals($orderHistory->id, $attachment->attachable->id);

        // Test inverse relationship
        $this->assertTrue($orderHistory->attachments->contains($attachment));
    }

    /**
     * Test relationship with user
     */
    public function test_uploaded_by_relationship()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'test.pdf',
            'file_path' => 'attachments/Order/' . $order->id . '/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $attachment->uploadedBy);
        $this->assertEquals($user->id, $attachment->uploadedBy->id);
    }

    /**
     * Test helper methods - getUrl
     */
    public function test_get_url_method()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'test.pdf',
            'file_path' => 'attachments/Order/' . $order->id . '/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        $expectedUrl = Storage::disk('public')->url('attachments/Order/' . $order->id . '/test.pdf');
        $this->assertEquals($expectedUrl, $attachment->getUrl());
    }

    /**
     * Test helper methods - getHumanReadableSize
     */
    public function test_get_human_readable_size_method()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $testCases = [
            ['size' => 512, 'expected' => '512 B'],
            ['size' => 1024, 'expected' => '1 KB'],
            ['size' => 1536, 'expected' => '1.5 KB'],
            ['size' => 1048576, 'expected' => '1 MB'],
            ['size' => 1572864, 'expected' => '1.5 MB'],
            ['size' => 1073741824, 'expected' => '1 GB'],
        ];

        foreach ($testCases as $testCase) {
            $attachment = Attachment::create([
                'attachable_type' => 'order',
                'attachable_id' => $order->id,
                'file_name' => 'test.pdf',
                'file_path' => 'attachments/test.pdf',
                'file_size' => $testCase['size'],
                'mime_type' => 'application/pdf',
                'uploaded_by' => $user->id
            ]);

            $this->assertEquals($testCase['expected'], $attachment->getHumanReadableSize());
        }
    }

    /**
     * Test helper methods - isImage
     */
    public function test_is_image_method()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $imageAttachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'photo.jpg',
            'file_path' => 'attachments/photo.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $user->id
        ]);

        $documentAttachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'document.pdf',
            'file_path' => 'attachments/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        $this->assertTrue($imageAttachment->isImage());
        $this->assertFalse($documentAttachment->isImage());
    }

    /**
     * Test helper methods - isDocument
     */
    public function test_is_document_method()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $wordDoc = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'document.docx',
            'file_path' => 'attachments/document.docx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploaded_by' => $user->id
        ]);

        $excelDoc = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'spreadsheet.xlsx',
            'file_path' => 'attachments/spreadsheet.xlsx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'uploaded_by' => $user->id
        ]);

        $imageFile = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'image.jpg',
            'file_path' => 'attachments/image.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $user->id
        ]);

        $this->assertTrue($wordDoc->isDocument());
        $this->assertTrue($excelDoc->isDocument());
        $this->assertFalse($imageFile->isDocument());
    }

    /**
     * Test helper methods - isPdf
     */
    public function test_is_pdf_method()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $pdfAttachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'document.pdf',
            'file_path' => 'attachments/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        $wordAttachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'document.docx',
            'file_path' => 'attachments/document.docx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploaded_by' => $user->id
        ]);

        $this->assertTrue($pdfAttachment->isPdf());
        $this->assertFalse($wordAttachment->isPdf());
    }

    /**
     * Test helper methods - getExtension
     */
    public function test_get_extension_method()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $testCases = [
            ['filename' => 'document.pdf', 'expected' => 'pdf'],
            ['filename' => 'image.jpg', 'expected' => 'jpg'],
            ['filename' => 'archive.tar.gz', 'expected' => 'gz'],
            ['filename' => 'noextension', 'expected' => ''],
        ];

        foreach ($testCases as $testCase) {
            $attachment = Attachment::create([
                'attachable_type' => 'order',
                'attachable_id' => $order->id,
                'file_name' => $testCase['filename'],
                'file_path' => 'attachments/' . $testCase['filename'],
                'file_size' => 1024,
                'mime_type' => 'application/octet-stream',
                'uploaded_by' => $user->id
            ]);

            $this->assertEquals($testCase['expected'], $attachment->getExtension());
        }
    }

    /**
     * Test scopes - images
     */
    public function test_images_scope()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Create image attachments
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'photo1.jpg',
            'file_path' => 'attachments/photo1.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $user->id
        ]);

        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'photo2.png',
            'file_path' => 'attachments/photo2.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
            'uploaded_by' => $user->id
        ]);

        // Create non-image attachment
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'document.pdf',
            'file_path' => 'attachments/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        $imageAttachments = Attachment::images()->get();

        $this->assertCount(2, $imageAttachments);
        foreach ($imageAttachments as $attachment) {
            $this->assertStringStartsWith('image/', $attachment->mime_type);
        }
    }

    /**
     * Test scopes - documents
     */
    public function test_documents_scope()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Create document attachments
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'document.pdf',
            'file_path' => 'attachments/document.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'spreadsheet.xlsx',
            'file_path' => 'attachments/spreadsheet.xlsx',
            'file_size' => 1024,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'uploaded_by' => $user->id
        ]);

        // Create image attachment
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'photo.jpg',
            'file_path' => 'attachments/photo.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $user->id
        ]);

        $documentAttachments = Attachment::documents()->get();

        $this->assertCount(2, $documentAttachments);
        foreach ($documentAttachments as $attachment) {
            $this->assertStringStartsWith('application/', $attachment->mime_type);
        }
    }

    /**
     * Test deletion of files and records
     */
    public function test_delete_removes_file_and_record()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        // Create a real file in the fake storage
        $file = UploadedFile::fake()->image('photo.jpg');
        $filePath = $file->store('attachments/Order/' . $order->id, 'public');

        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'photo.jpg',
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $user->id
        ]);

        // Verify file exists
        Storage::disk('public')->assertExists($filePath);

        // Delete attachment
        $attachment->delete();

        // Verify file is deleted
        Storage::disk('public')->assertMissing($filePath);

        // Verify record is deleted
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    /**
     * Test that delete works even if the physical file does not exist
     */
    public function test_delete_handles_missing_physical_file()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $order->id,
            'file_name' => 'non-existent.pdf',
            'file_path' => 'attachments/non-existent.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id
        ]);

        // The file does not exist in the storage
        Storage::disk('public')->assertMissing($attachment->file_path);

        // Delete should not throw an exception
        $result = $attachment->delete();

        $this->assertTrue($result);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    /**
     * Test MIME type constants
     */
    public function test_mime_type_constants()
    {
        $this->assertEquals('application/pdf', Attachment::MIME_PDF);
        $this->assertEquals('image/jpeg', Attachment::MIME_JPG);
        $this->assertEquals('image/png', Attachment::MIME_PNG);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', Attachment::MIME_DOCX);
    }

    /**
     * Test MIME type groups
     */
    public function test_mime_type_groups()
    {
        $this->assertContains('image/jpeg', Attachment::IMAGE_MIME_TYPES);
        $this->assertContains('image/png', Attachment::IMAGE_MIME_TYPES);

        $this->assertContains('application/pdf', Attachment::DOCUMENT_MIME_TYPES);
        $this->assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', Attachment::DOCUMENT_MIME_TYPES);

        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Attachment::SPREADSHEET_MIME_TYPES);
        $this->assertContains('text/csv', Attachment::SPREADSHEET_MIME_TYPES);
    }
}
