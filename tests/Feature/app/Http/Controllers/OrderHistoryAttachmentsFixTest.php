<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shows attachments only for attachment related history entries', function () {
    Storage::fake('public');

    // Create admin user
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($admin);

    // Create test order
    $order = Order::factory()->createQuietly([
        'customer_id' => User::factory()->create(['role' => UserRole::CUSTOMER->value])->id,
        'created_by' => $admin->id,
        'assigned_to' => $admin->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
    ]);

    // 1. Make a non-attachment change (status change)
    $resp1 = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
    ]);
    $resp1->assertOk();

    // 2. Upload an attachment (this should create attachment history)
    $file = UploadedFile::fake()->image('test-image.jpg', 100, 100);
    $response = $this->postJson("/api/v1/orders/{$order->uuid}/attachments", [
        'file' => $file,
        'name' => 'test-image.jpg',
    ]);
    $response->assertCreated();

    // 3. Make another non-attachment change
    $resp2 = $this->putJson("/api/v1/orders/{$order->uuid}", [
        'title' => 'Updated Title',
    ]);
    $resp2->assertOk();

    // 4. Get order history
    $historyResponse = $this->getJson("/api/v1/orders/{$order->uuid}/history");

    $historyResponse->assertOk();
    $historyData = $historyResponse->json('data');

    // Verify we have at least 3 history entries (status, attachments, title)
    expect(count($historyData))->toBeGreaterThanOrEqual(3);

    // Find the attachment-related history entry
    $attachmentHistory = collect($historyData)->firstWhere('field_changed', 'attachments');
    expect($attachmentHistory)->not->toBeNull('Should have attachment history entry');

    // The attachment history entry should have attachment data
    expect($attachmentHistory['attachments'])->not->toBeEmpty('Attachment history should have attachment data');
    expect($attachmentHistory['attachments'][0]['file_name'])->toEqual('test-image.jpg');

    // Non-attachment history entries should have empty attachments
    $nonAttachmentHistory = collect($historyData)->where('field_changed', '!=', 'attachments');
    foreach ($nonAttachmentHistory as $history) {
        expect($history['attachments'])->toBeEmpty("Non-attachment history entry (field: {$history['field_changed']}) should have empty attachments array");
    }
});
