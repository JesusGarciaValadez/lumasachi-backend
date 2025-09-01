<?php

namespace Tests\Feature\app\Traits;

use Tests\TestCase;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Attachment;
use App\Models\User;
use App\Traits\HasAttachments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;

final class HasAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected Order $order;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Create a test user
        $this->user = User::factory()->create();

        // Create an order instance for testing
        $this->order = Order::factory()->createQuietly(['title' => 'Test Order']);
    }

    /**
     * Test attachments relationship
     */
    #[Test]
    public function it_checks_if_attachments_relationship(): void
    {
        $attachment = Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'test.pdf',
            'file_path' => 'attachments/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->user->id
        ]);

        $this->order->refresh();
        $this->assertCount(1, $this->order->attachments);
        $this->assertTrue($this->order->attachments->contains($attachment));
    }

    /**
     * Test attach method
     */
    #[Test]
    public function it_checks_if_attach_method(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $attachment = $this->order->attach($file, $this->user->id);

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('photo.jpg', $attachment->file_name);
        $this->assertEquals($file->getSize(), $attachment->file_size);
        $this->assertEquals('image/jpeg', $attachment->mime_type);
        $this->assertEquals($this->user->id, $attachment->uploaded_by);

        // Check file path format
        $this->assertStringContainsString('attachments/Order/' . $this->order->id, $attachment->file_path);

        // Verify file was stored
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    /**
     * Test attach method with custom disk
     */
    #[Test]
    public function it_checks_if_attach_method_with_custom_disk(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $attachment = $this->order->attach($file, $this->user->id, 'local');

        Storage::disk('local')->assertExists($attachment->file_path);
    }

    /**
     * Test detach method
     */
    #[Test]
    public function it_checks_if_detach_method(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');
        $attachment = $this->order->attach($file, $this->user->id);

        // Verify attachment exists
        $this->assertCount(1, $this->order->attachments);
        Storage::disk('public')->assertExists($attachment->file_path);

        // Detach the attachment
        $result = $this->order->detach($attachment->uuid);

        $this->assertTrue($result);
        $this->assertCount(0, $this->order->fresh()->attachments);
        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertDatabaseMissing('attachments', ['uuid' => $attachment->uuid]);
    }

    /**
     * Test detach method with non-existent attachment
     */
    #[Test]
    public function it_checks_if_detach_method_with_non_existent_attachment(): void
    {
        // Use a valid UUID format that doesn't exist
        $result = $this->order->detach('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($result);
    }

    /**
     * Test hasAttachments method
     */
    #[Test]
    public function it_checks_if_has_attachments_method(): void
    {
        $this->assertFalse($this->order->hasAttachments());

        $file = UploadedFile::fake()->image('photo.jpg');
        $this->order->attach($file, $this->user->id);

        $this->assertTrue($this->order->hasAttachments());
    }

    /**
     * Test getAttachmentsByType method
     */
    #[Test]
    public function it_checks_if_get_attachments_by_type_method(): void
    {
        // Create various attachments
        $image = UploadedFile::fake()->image('photo.jpg');
        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $doc = UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->order->attach($image, $this->user->id);
        $this->order->attach($pdf, $this->user->id);
        $this->order->attach($doc, $this->user->id);

        // Test partial type matching
        $imageAttachments = $this->order->getAttachmentsByType('image');
        $this->assertCount(1, $imageAttachments);
        $this->assertEquals('photo.jpg', $imageAttachments->first()->file_name);

        // Test exact MIME type matching
        $pdfAttachments = $this->order->getAttachmentsByType('application/pdf');
        $this->assertCount(1, $pdfAttachments);
        $this->assertEquals('document.pdf', $pdfAttachments->first()->file_name);

        // Test application type matching
        $appAttachments = $this->order->getAttachmentsByType('application');
        $this->assertCount(2, $appAttachments);
    }

    /**
     * Test getImageAttachments method
     */
    #[Test]
    public function it_checks_if_get_image_attachments_method(): void
    {
        $image1 = UploadedFile::fake()->image('photo1.jpg');
        $image2 = UploadedFile::fake()->image('photo2.png');
        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->order->attach($image1, $this->user->id);
        $this->order->attach($image2, $this->user->id);
        $this->order->attach($pdf, $this->user->id);

        $imageAttachments = $this->order->getImageAttachments();

        $this->assertCount(2, $imageAttachments);
        foreach ($imageAttachments as $attachment) {
            $this->assertStringStartsWith('image/', $attachment->mime_type);
        }
    }

    /**
     * Test getDocumentAttachments method
     */
    #[Test]
    public function it_checks_if_get_document_attachments_method(): void
    {
        $image = UploadedFile::fake()->image('photo.jpg');
        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $word = UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->order->attach($image, $this->user->id);
        $this->order->attach($pdf, $this->user->id);
        $this->order->attach($word, $this->user->id);

        $documentAttachments = $this->order->getDocumentAttachments();

        $this->assertCount(2, $documentAttachments);
        foreach ($documentAttachments as $attachment) {
            $this->assertStringStartsWith('application/', $attachment->mime_type);
        }
    }

    /**
     * Test getTotalAttachmentsSize method
     */
    #[Test]
    public function it_checks_if_get_total_attachments_size_method(): void
    {
        $this->assertEquals(0, $this->order->getTotalAttachmentsSize());

        // Create attachments with known sizes
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'file1.pdf',
            'file_path' => 'attachments/file1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->user->id
        ]);

        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'file2.pdf',
            'file_path' => 'attachments/file2.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->user->id
        ]);

        $this->order->refresh();
        $this->assertEquals(3072, $this->order->getTotalAttachmentsSize());
    }

    /**
     * Test getTotalAttachmentsSizeFormatted method
     */
    #[Test]
    public function it_checks_if_get_total_attachments_size_formatted_method(): void
    {
        // Test with no attachments
        $this->assertEquals('0 B', $this->order->getTotalAttachmentsSizeFormatted());

        // Create attachment with specific size
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'file.pdf',
            'file_path' => 'attachments/file.pdf',
            'file_size' => 1536, // 1.5 KB
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->user->id
        ]);

        $this->order->refresh();
        $this->assertEquals('1.5 KB', $this->order->getTotalAttachmentsSizeFormatted());

        // Add more attachments to test MB
        Attachment::create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'large_file.pdf',
            'file_path' => 'attachments/large_file.pdf',
            'file_size' => 1048576, // 1 MB
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->user->id
        ]);

        $this->order->refresh();
        $totalSizeBytes = 1536 + 1048576;
        $expectedSize = round($totalSizeBytes / 1024 / 1024, 2) . ' MB';
        $this->assertEquals($expectedSize, $this->order->getTotalAttachmentsSizeFormatted());
    }

    /**
     * Test detachAll method
     */
    #[Test]
    public function it_checks_if_detach_all_method(): void
    {
        // Create multiple attachments
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
            UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')
        ];

        $filePaths = [];
        foreach ($files as $file) {
            $attachment = $this->order->attach($file, $this->user->id);
            $filePaths[] = $attachment->file_path;
        }

        // Verify attachments exist
        $this->assertCount(3, $this->order->attachments);
        foreach ($filePaths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        // Detach all
        $count = $this->order->detachAll();

        $this->assertEquals(3, $count);
        $this->assertCount(0, $this->order->fresh()->attachments);

        // Verify files are deleted
        foreach ($filePaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * Test attachment file name uniqueness
     */
    #[Test]
    public function it_checks_if_attachment_file_name_uniqueness(): void
    {
        $file1 = UploadedFile::fake()->image('photo.jpg');
        $file2 = UploadedFile::fake()->image('photo.jpg');

        $attachment1 = $this->order->attach($file1, $this->user->id);
        $attachment2 = $this->order->attach($file2, $this->user->id);

        // Both should have the same original file name
        $this->assertEquals('photo.jpg', $attachment1->file_name);
        $this->assertEquals('photo.jpg', $attachment2->file_name);

        // But different file paths (due to UUID prefix)
        $this->assertNotEquals($attachment1->file_path, $attachment2->file_path);

        // Both files should exist
        Storage::disk('public')->assertExists($attachment1->file_path);
        Storage::disk('public')->assertExists($attachment2->file_path);
    }

    /**
     * Test with Order model
     */
    #[Test]
    public function it_checks_if_trait_with_order_model(): void
    {
        $order = Order::factory()->createQuietly();

        // Order model should have the attachments method
        $this->assertTrue(method_exists($order, 'attachments'));
        $this->assertTrue(method_exists($order, 'attach'));
        $this->assertTrue(method_exists($order, 'detach'));
        $this->assertTrue(method_exists($order, 'hasAttachments'));

        // Test attaching a file
        $file = UploadedFile::fake()->image('order-photo.jpg');
        $attachment = $order->attach($file, $this->user->id);

        $this->assertCount(1, $order->attachments);
        $this->assertEquals($order->id, $attachment->attachable_id);
        $this->assertEquals('order', $attachment->attachable_type);
    }

    /**
     * Test with OrderHistory model
     */
    #[Test]
    public function it_checks_if_trait_with_order_history_model(): void
    {
        $order = Order::factory()->createQuietly();
        $orderHistory = OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Status changed',
            'created_by' => $this->user->id
        ]);

        // OrderHistory model should have the attachments method
        $this->assertTrue(method_exists($orderHistory, 'attachments'));
        $this->assertTrue(method_exists($orderHistory, 'attach'));
        $this->assertTrue(method_exists($orderHistory, 'detach'));
        $this->assertTrue(method_exists($orderHistory, 'hasAttachments'));

        // Test attaching a file
        $file = UploadedFile::fake()->create('history-note.pdf', 200, 'application/pdf');
        $attachment = $orderHistory->attach($file, $this->user->id);

        $this->assertCount(1, $orderHistory->attachments);
        $this->assertEquals($orderHistory->id, $attachment->attachable_id);
        $this->assertEquals('order_history', $attachment->attachable_type);
    }
}
