<?php

declare(strict_types=1);

namespace Tests\Unit\app\Models;

use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    #[Test]
    public function it_checks_if_can_create_attachment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        $attachment = $order->attachments()->create(Attachment::factory()->raw([
            'uploaded_by' => $user->id,
        ]));

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals($user->id, $attachment->uploaded_by);
    }

    /**
     * Test polymorphic relationships with Order
     */
    #[Test]
    public function it_checks_if_polymorphic_relationship_with_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        $attachment = $order->attachments()->create(Attachment::factory()->raw([
            'uploaded_by' => $user->id,
        ]));

        // Test attachable relationship
        $this->assertInstanceOf(Order::class, $attachment->attachable);
        $this->assertEquals($order->id, $attachment->attachable->id);

        // Test inverse relationship
        $this->assertTrue($order->fresh()->attachments->contains($attachment));
    }

    /**
     * Test polymorphic relationships with OrderHistory
     */
    #[Test]
    public function it_checks_if_polymorphic_relationship_with_order_history(): void
    {
        $user = User::factory()->create();
        $orderHistory = OrderHistory::factory()->create();

        $attachment = $orderHistory->attachments()->create(Attachment::factory()->raw([
            'uploaded_by' => $user->id,
        ]));

        // Test attachable relationship
        $this->assertInstanceOf(OrderHistory::class, $attachment->attachable);
        $this->assertEquals($orderHistory->id, $attachment->attachable->id);

        // Test inverse relationship
        $this->assertTrue($orderHistory->fresh()->attachments->contains($attachment));
    }

    /**
     * Test relationship with user
     */
    #[Test]
    public function it_checks_if_uploaded_by_relationship(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        $attachment = $order->attachments()->create(Attachment::factory()->raw([
            'uploaded_by' => $user->id,
        ]));

        $this->assertInstanceOf(User::class, $attachment->uploadedBy);
        $this->assertEquals($user->id, $attachment->uploadedBy->id);
    }

    /**
     * Test helper methods - getUrl
     */
    #[Test]
    public function it_checks_if_get_url_method(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        $attachment = $order->attachments()->create(Attachment::factory()->raw([
            'file_path' => 'attachments/Order/'.$order->id.'/test.pdf',
            'uploaded_by' => $user->id,
        ]));

        $expectedUrl = Storage::disk('public')->url('attachments/Order/'.$order->id.'/test.pdf');
        $this->assertEquals($expectedUrl, $attachment->getUrl());
    }

    /**
     * Test helper methods - getHumanReadableSize
     */
    #[Test]
    public function it_checks_if_get_human_readable_size_method(): void
    {
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

            $this->assertEquals($testCase['expected'], $attachment->getHumanReadableSize());
        }
    }

    /**
     * Test helper methods - isImage
     */
    #[Test]
    public function it_checks_if_is_image_method(): void
    {
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

        $this->assertTrue($imageAttachment->isImage());
        $this->assertFalse($documentAttachment->isImage());
    }

    /**
     * Test helper methods - isDocument
     */
    #[Test]
    public function it_checks_if_is_document_method(): void
    {
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

        $this->assertTrue($wordDoc->isDocument());
        $this->assertTrue($excelDoc->isDocument());
        $this->assertFalse($imageFile->isDocument());
    }

    /**
     * Test helper methods - isPdf
     */
    #[Test]
    public function it_checks_if_is_pdf_method(): void
    {
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

        $this->assertTrue($pdfAttachment->isPdf());
        $this->assertFalse($wordAttachment->isPdf());
    }

    /**
     * Test helper methods - getExtension
     */
    #[Test]
    public function it_checks_if_get_extension_method(): void
    {
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

            $this->assertEquals($testCase['expected'], $attachment->getExtension());
        }
    }

    /**
     * Test scopes - images
     */
    #[Test]
    public function it_checks_if_images_scope(): void
    {
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

        $this->assertCount(2, $imageAttachments);
        foreach ($imageAttachments as $attachment) {
            $this->assertStringStartsWith('image/', $attachment->mime_type);
        }
    }

    /**
     * Test scopes - documents
     */
    #[Test]
    public function it_checks_if_documents_scope(): void
    {
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

        $this->assertCount(2, $documentAttachments);
        foreach ($documentAttachments as $attachment) {
            $this->assertStringStartsWith('application/', $attachment->mime_type);
        }
    }

    /**
     * Test deletion of files and records
     */
    #[Test]
    public function it_checks_if_delete_removes_file_and_record(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Create a real file in the fake storage
        $file = UploadedFile::fake()->image('photo.jpg');
        $filePath = $file->store('attachments/Order/'.$order->id, 'public');

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
    }

    /**
     * Test that delete works even if the physical file does not exist
     */
    #[Test]
    public function it_checks_if_delete_handles_missing_physical_file(): void
    {
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

        $this->assertTrue($result);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    /**
     * Test MIME type constants
     */
    #[Test]
    public function it_checks_if_mime_type_constants(): void
    {
        $this->assertEquals('application/pdf', Attachment::MIME_PDF);
        $this->assertEquals('image/jpeg', Attachment::MIME_JPG);
        $this->assertEquals('image/png', Attachment::MIME_PNG);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', Attachment::MIME_DOCX);
    }

    /**
     * Test MIME type groups
     */
    #[Test]
    public function it_checks_if_mime_type_groups(): void
    {
        $this->assertContains('image/jpeg', Attachment::IMAGE_MIME_TYPES);
        $this->assertContains('image/png', Attachment::IMAGE_MIME_TYPES);

        $this->assertContains('application/pdf', Attachment::DOCUMENT_MIME_TYPES);
        $this->assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', Attachment::DOCUMENT_MIME_TYPES);

        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Attachment::SPREADSHEET_MIME_TYPES);
        $this->assertContains('text/csv', Attachment::SPREADSHEET_MIME_TYPES);
    }
}
