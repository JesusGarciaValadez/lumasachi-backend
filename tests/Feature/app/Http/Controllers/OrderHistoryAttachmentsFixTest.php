<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Models\Category;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderHistoryAttachmentsFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that order history shows attachments only for attachment-related history entries
     */
    #[Test]
    public function it_shows_attachments_only_for_attachment_related_history_entries()
    {
        Storage::fake('public');

        // Create admin user
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($admin);
        
        // Create category first
        $category = Category::factory()->create();
        
        // Create test order
        $order = Order::factory()->createQuietly([
            'customer_id' => User::factory()->create(['role' => UserRole::CUSTOMER->value])->id,
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
            'status' => OrderStatus::OPEN->value,
            'category_id' => $category->id
        ]);

        // 1. Make a non-attachment change (status change)
        $this->putJson("/api/v1/orders/{$order->id}", [
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        // 2. Upload an attachment (this should create attachment history)
        $file = UploadedFile::fake()->image('test-image.jpg', 100, 100);
        $response = $this->postJson("/api/v1/orders/{$order->id}/attachments", [
            'file' => $file,
            'name' => 'test-image.jpg'
        ]);
        $response->assertCreated();

        // 3. Make another non-attachment change
        $this->putJson("/api/v1/orders/{$order->id}", [
            'title' => 'Updated Title'
        ]);

        // 4. Get order history
        $historyResponse = $this->getJson("/api/v1/orders/{$order->id}/history");

        $historyResponse->assertOk();
        $historyData = $historyResponse->json('data');

        // Verify we have at least 3 history entries (could be more depending on what fields changed)
        $this->assertGreaterThanOrEqual(3, count($historyData));

        // Find the attachment-related history entry
        $attachmentHistory = collect($historyData)->firstWhere('field_changed', 'attachments');
        $this->assertNotNull($attachmentHistory, 'Should have attachment history entry');
        
        // The attachment history entry should have attachment data
        $this->assertNotEmpty($attachmentHistory['attachments'], 'Attachment history should have attachment data');
        $this->assertEquals('test-image.jpg', $attachmentHistory['attachments'][0]['file_name']);

        // Non-attachment history entries should have empty attachments
        $nonAttachmentHistory = collect($historyData)->where('field_changed', '!=', 'attachments');
        foreach ($nonAttachmentHistory as $history) {
            $this->assertEmpty($history['attachments'], "Non-attachment history entry (field: {$history['field_changed']}) should have empty attachments array");
        }
    }
}