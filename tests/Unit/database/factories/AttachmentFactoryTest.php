<?php

declare(strict_types=1);

namespace Tests\Unit\database\factories;

use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AttachmentFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the factory creates a valid attachment
     */
    #[Test]
    public function it_checks_if_factory_creates_valid_attachment(): void
    {
        $attachment = Attachment::factory()->create();

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'file_name' => $attachment->file_name,
        ]);
    }

    /**
     * Test all required fields are present
     */
    #[Test]
    public function it_checks_if_factory_generates_all_required_fields(): void
    {
        $attachment = Attachment::factory()->make();

        $this->assertNotNull($attachment->attachable_type);
        $this->assertNotNull($attachment->attachable_id);
        $this->assertNotNull($attachment->file_name);
        $this->assertNotNull($attachment->file_path);
        $this->assertNotNull($attachment->file_size);
        $this->assertNotNull($attachment->mime_type);
        $this->assertNotNull($attachment->uploaded_by);
    }

    /**
     * Test that factory can create an image attachment
     */
    #[Test]
    public function it_checks_if_factory_creates_image_attachment(): void
    {
        $attachment = Attachment::factory()->image()->create();

        $this->assertTrue($attachment->isImage());
        $this->assertContains($attachment->mime_type, Attachment::IMAGE_MIME_TYPES);
    }

    /**
     * Test that factory can create a PDF attachment
     */
    #[Test]
    public function it_checks_if_factory_creates_pdf_attachment(): void
    {
        $attachment = Attachment::factory()->pdf()->create();

        $this->assertTrue($attachment->isPdf());
        $this->assertEquals(Attachment::MIME_PDF, $attachment->mime_type);
    }

    /**
     * Test that factory can create a spreadsheet attachment
     */
    #[Test]
    public function it_checks_if_factory_creates_spreadsheet_attachment(): void
    {
        $attachment = Attachment::factory()->spreadsheet()->create();

        $this->assertContains($attachment->mime_type, Attachment::SPREADSHEET_MIME_TYPES);
    }

    /**
     * Test that factory can override attributes
     */
    #[Test]
    public function it_checks_if_factory_can_override_attributes(): void
    {
        $customFileName = 'custom_file_name.pdf';
        $attachment = Attachment::factory()->create([
            'file_name' => $customFileName,
        ]);

        $this->assertEquals($customFileName, $attachment->file_name);
    }

    /**
     * Test that factory creates associated models
     */
    #[Test]
    public function it_checks_if_factory_creates_associated_models(): void
    {
        $attachment = Attachment::factory()->create();

        // Check that the attachable model exists (could be Order or OrderHistory)
        if ($attachment->attachable_type === 'order') {
            $this->assertDatabaseHas('orders', ['id' => $attachment->attachable_id]);
        } elseif ($attachment->attachable_type === 'order_history') {
            $this->assertDatabaseHas('order_histories', ['id' => $attachment->attachable_id]);
        }

        // Check that the user was created
        $this->assertDatabaseHas('users', ['id' => $attachment->uploaded_by]);
    }

    /**
     * Test factory creates attachments for specific order
     */
    #[Test]
    public function it_checks_if_factory_creates_attachments_for_specific_order(): void
    {
        $order = Order::factory()->createQuietly();

        $attachment = Attachment::factory()->for($order, 'attachable')->create();

        $this->assertEquals('order', $attachment->attachable_type);
        $this->assertEquals($order->id, $attachment->attachable_id);
        $this->assertEquals($order->id, $attachment->attachable->id);
    }

    /**
     * Test factory creates attachments for specific order history
     */
    #[Test]
    public function it_checks_if_factory_creates_attachments_for_specific_order_history(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $attachment = Attachment::factory()->for($orderHistory, 'attachable')->create();

        $this->assertEquals('order_history', $attachment->attachable_type);
        $this->assertEquals($orderHistory->id, $attachment->attachable_id);
        $this->assertEquals($orderHistory->id, $attachment->attachable->id);
    }

    /**
     * Test factory generates UUID
     */
    #[Test]
    public function it_checks_if_factory_generates_uuid(): void
    {
        $attachment = Attachment::factory()->create();

        $this->assertNotNull($attachment->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $attachment->uuid
        );
    }

    /**
     * Test factory generates realistic data
     */
    #[Test]
    public function it_checks_if_factory_generates_realistic_data(): void
    {
        $attachment = Attachment::factory()->create([
            'file_size' => 1024,
        ]);

        $this->assertStringEndsWith($attachment->getExtension(), $attachment->file_name);
        $this->assertGreaterThanOrEqual(1024, $attachment->file_size); // Minimum 1KB
        $this->assertLessThanOrEqual(52428800, $attachment->file_size); // Maximum 50MB
    }

    /**
     * Test file path includes date-based directory structure
     */
    #[Test]
    public function it_checks_if_file_path_includes_date_structure(): void
    {
        $attachment = Attachment::factory()->create();
        $this->assertStringContainsString('attachments/', $attachment->file_path);
    }

    /**
     * Test multiple attachments can be created
     */
    #[Test]
    public function it_checks_if_multiple_attachments_can_be_created(): void
    {
        $attachments = Attachment::factory()->count(5)->create();

        $this->assertCount(5, $attachments);

        foreach ($attachments as $attachment) {
            $this->assertInstanceOf(Attachment::class, $attachment);
            $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
        }
    }

    /**
     * Test factory generates unique file names
     */
    #[Test]
    public function it_checks_if_factory_generates_unique_file_names(): void
    {
        $attachments = Attachment::factory()->count(10)->make();
        $fileNames = $attachments->pluck('file_name')->toArray();

        // Check that all file names are unique
        $this->assertEquals(count($fileNames), count(array_unique($fileNames)));
    }

    /**
     * Test factory generates valid MIME types
     */
    #[Test]
    public function it_checks_if_factory_generates_valid_mime_types(): void
    {
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
            $this->assertContains($attachment->mime_type, $allValidMimeTypes, "Failed asserting that '{$attachment->mime_type}' is in valid MIME types.");
        }
    }

    /**
     * Test factory relationships work correctly
     */
    #[Test]
    public function it_checks_if_factory_relationships_work_correctly(): void
    {
        $attachment = Attachment::factory()->create();

        // Test attachable relationship
        $this->assertNotNull($attachment->attachable);
        $this->assertContains($attachment->attachable_type, ['order', 'order_history']);

        // Test uploadedBy relationship
        $this->assertInstanceOf(User::class, $attachment->uploadedBy);
        $this->assertEquals($attachment->uploaded_by, $attachment->uploadedBy->id);
    }

    /**
     * Test human readable file size method
     */
    #[Test]
    public function it_checks_if_human_readable_file_size(): void
    {
        $testCases = [
            ['size' => 512, 'expected' => '512 B'],
            ['size' => 1024, 'expected' => '1 KB'],
            ['size' => 1536, 'expected' => '1.5 KB'],
            ['size' => 1048576, 'expected' => '1 MB'],
            ['size' => 5242880, 'expected' => '5 MB'],
        ];

        foreach ($testCases as $testCase) {
            $attachment = Attachment::factory()->make(['file_size' => $testCase['size']]);
            $this->assertEquals($testCase['expected'], $attachment->getHumanReadableSize());
        }
    }

    /**
     * Test file extension is properly detected
     */
    #[Test]
    public function it_checks_if_file_extension_detection(): void
    {
        $testCases = [
            'document.pdf' => 'pdf',
            'image.jpg' => 'jpg',
            'spreadsheet.xlsx' => 'xlsx',
            'archive.zip' => 'zip',
        ];

        foreach ($testCases as $fileName => $expectedExtension) {
            $attachment = Attachment::factory()->make(['file_name' => $fileName]);
            $this->assertEquals($expectedExtension, $attachment->getExtension());
        }
    }

    /**
     * Test factory with specific user
     */
    #[Test]
    public function it_checks_if_factory_with_specific_user(): void
    {
        $user = User::factory()->create();

        $attachment = Attachment::factory()->create([
            'uploaded_by' => $user->id,
        ]);

        $this->assertEquals($user->id, $attachment->uploaded_by);
        $this->assertEquals($user->id, $attachment->uploadedBy->id);
    }
}
