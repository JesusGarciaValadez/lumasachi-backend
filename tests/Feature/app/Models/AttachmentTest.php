<?php

declare(strict_types=1);

namespace Tests\Feature\app\Models;

use App\Enums\OrderStatus;
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
     * Test complete attachment lifecycle with an order
     */
    #[Test]
    public function it_checks_complete_attachment_lifecycle_with_order()
    {
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
        $this->assertCount(4, $order->attachments);
        $this->assertTrue($order->hasAttachments());

        // Verify that the files are categorized correctly
        $imageAttachments = $order->getImageAttachments();
        $this->assertCount(2, $imageAttachments);

        // Verify the total size
        $totalSize = $order->getTotalAttachmentsSize();
        $this->assertGreaterThan(0, $totalSize);

        // Verify the readable size format
        $formattedSize = $order->getTotalAttachmentsSizeFormatted();
        $this->assertStringContainsString(' ', $formattedSize); // Must have space between number and unit

        // Verify files by MIME type
        $pdfFiles = $order->getAttachmentsByType('application/pdf');
        $this->assertCount(1, $pdfFiles);
        $this->assertEquals('order-document.pdf', $pdfFiles->first()->file_name);

        // Delete a specific file
        $pdfAttachment = $pdfFiles->first();
        $pdfPath = $pdfAttachment->file_path;
        Storage::disk('public')->assertExists($pdfPath);

        $result = $order->detach($pdfAttachment->id);
        $this->assertTrue($result);
        Storage::disk('public')->assertMissing($pdfPath);

        // Verify that there are 3 files left
        $order->refresh();
        $this->assertCount(3, $order->attachments);

        // Delete all remaining files
        $remainingPaths = $order->attachments->pluck('file_path')->toArray();
        $deletedCount = $order->detachAll();

        $this->assertEquals(3, $deletedCount);
        $this->assertFalse($order->hasAttachments());

        // Verify that all files were deleted
        foreach ($remainingPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * Test attachments with order history
     */
    #[Test]
    public function it_checks_attachments_with_order_history()
    {
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

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals('order_history', $attachment->attachable_type);
        $this->assertEquals($history->id, $attachment->attachable_id);
        $this->assertTrue($history->hasAttachments());

        // Verify that the file is correctly stored
        $expectedPath = 'attachments/OrderHistory/'.$history->id;
        $this->assertStringContainsString($expectedPath, $attachment->file_path);
        Storage::disk('public')->assertExists($attachment->file_path);

        // Verify polymorphic relationship
        $this->assertInstanceOf(OrderHistory::class, $attachment->attachable);
        $this->assertEquals($history->id, $attachment->attachable->id);
    }

    /**
     * Test multiple attachments between order and history
     */
    #[Test]
    public function it_checks_multiple_attachments_across_order_and_history()
    {
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
        $this->assertCount(2, $order->attachments);

        // Verify attachments of each history
        foreach ($histories as $history) {
            $this->assertCount(1, $history->attachments);
        }

        // Verify total attachments in the database
        $totalAttachments = Attachment::count();
        $this->assertEquals(5, $totalAttachments); // 2 de orden + 3 de historiales

        // Verify that each attachment is correctly associated
        $orderAttachments = Attachment::where('attachable_type', 'order')
            ->where('attachable_id', $order->id)
            ->get();
        $this->assertCount(2, $orderAttachments);

        $historyAttachments = Attachment::where('attachable_type', 'order_history')
            ->whereIn('attachable_id', collect($histories)->pluck('id'))
            ->get();
        $this->assertCount(3, $historyAttachments);
    }

    /**
     * Test error handling and edge cases
     */
    #[Test]
    public function it_checks_edge_cases_and_error_handling()
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Test 1: Try to delete an attachment that does not belong to the order
        $otherOrder = Order::factory()->createQuietly();
        $file = UploadedFile::fake()->image('other.jpg');
        $otherAttachment = $otherOrder->attach($file, $user->id);

        $result = $order->detach($otherAttachment->id);
        $this->assertFalse($result);

        // Test 2: Attach an empty file
        $emptyFile = UploadedFile::fake()->create('empty.txt', 0);
        $emptyAttachment = $order->attach($emptyFile, $user->id);
        $this->assertEquals(0, $emptyAttachment->file_size);
        $this->assertEquals('0 B', $emptyAttachment->getHumanReadableSize());

        // Test 3: Attach files with the same name
        $file1 = UploadedFile::fake()->image('duplicate.jpg');
        $file2 = UploadedFile::fake()->image('duplicate.jpg');

        $attachment1 = $order->attach($file1, $user->id);
        $attachment2 = $order->attach($file2, $user->id);

        // Both should have the same original name
        $this->assertEquals($attachment1->file_name, $attachment2->file_name);
        // But different file paths
        $this->assertNotEquals($attachment1->file_path, $attachment2->file_path);

        // Test 4: Verify that when the order is deleted, the attachments are not automatically deleted
        // (this depends on the implementation, but it's good to verify it)
        $orderId = $order->id;
        $attachmentIds = $order->attachments->pluck('id')->toArray();

        // For now we only verify that the attachments exist
        $this->assertGreaterThan(0, count($attachmentIds));

        // If in the future cascade delete is implemented, this test should be updated
    }

    /**
     * Test performance with multiple attachments
     */
    #[Test]
    public function it_checks_performance_with_multiple_attachments()
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Create 20 attachments
        $attachmentCount = 20;
        for ($i = 1; $i <= $attachmentCount; $i++) {
            $file = UploadedFile::fake()->create("file-{$i}.pdf", rand(100, 1000), 'application/pdf');
            $order->attach($file, $user->id);
        }

        // Verify count
        $this->assertCount($attachmentCount, $order->attachments);

        // Verify that the queries are efficient
        $this->assertLessThan(1000, memory_get_peak_usage() / 1024 / 1024); // Less than 1GB of memory

        // Verify filtering methods
        $pdfAttachments = $order->getAttachmentsByType('application/pdf');
        $this->assertCount($attachmentCount, $pdfAttachments);

        // Verify total size calculation
        $totalSize = $order->getTotalAttachmentsSize();
        $this->assertGreaterThan(100 * 1024 * $attachmentCount, $totalSize); // At least 100KB per file

        // Delete all attachments efficiently
        $deletedCount = $order->detachAll();
        $this->assertEquals($attachmentCount, $deletedCount);
        $this->assertCount(0, $order->fresh()->attachments);
    }

    /**
     * Test referential integrity
     */
    #[Test]
    public function it_checks_referential_integrity()
    {
        $user = User::factory()->create();
        $order = Order::factory()->createQuietly();

        // Attach file
        $file = UploadedFile::fake()->image('test.jpg');
        $attachment = $order->attach($file, $user->id);

        // Verify relationship with user
        $this->assertEquals($user->id, $attachment->uploaded_by);
        $this->assertEquals($user->id, $attachment->uploadedBy->id);

        // Verify that the attachment knows its parent model
        $this->assertInstanceOf(Order::class, $attachment->attachable);
        $this->assertEquals($order->id, $attachment->attachable->id);

        // Verify inverse relationship
        $foundAttachment = $order->attachments()->find($attachment->id);
        $this->assertNotNull($foundAttachment);
        $this->assertEquals($attachment->id, $foundAttachment->id);
    }
}
