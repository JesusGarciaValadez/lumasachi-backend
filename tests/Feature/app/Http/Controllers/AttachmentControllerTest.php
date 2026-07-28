<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Storage::fake('public');

    // Create users with different roles
    $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
    $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

    // Create test orders
    $this->order = Order::factory()->createQuietly([
        'uuid' => Str::uuid7()->toString(),
        'customer_id' => $this->customer->id,
        'created_by' => $this->employee->id,
        'assigned_to' => $this->employee->id,
        'status' => OrderStatus::Open->value,
    ]);

    $this->otherOrder = Order::factory()->createQuietly([
        'uuid' => Str::uuid7()->toString(),
        'customer_id' => User::factory()->create(['role' => UserRole::CUSTOMER->value])->id,
        'created_by' => $this->employee2->id,
        'assigned_to' => $this->employee2->id,
        'status' => OrderStatus::Open->value,
    ]);
});
afterEach(function () {
    // Clean up any test files created during tests
    Storage::fake('public');
});
it('checks view order attachments', function () {
    $this->actingAs($this->employee);

    // Create some attachments
    Attachment::factory()->count(2)->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'uploaded_by' => $this->employee->id,
    ]);

    $first = $this->getJson("/api/v1/orders/{$this->order->uuid}/attachments");

    $first->assertOk()
        ->assertHeader('X-Cache', 'MISS')
        ->assertJsonStructure([
            'order_id',
            'attachments' => [
                '*' => [
                    'id',
                    'file_name',
                    'file_path',
                    'mime_type',
                    'file_size',
                    'human_file_size',
                    'created_at',
                    'is_image',
                    'is_document',
                    'extension',
                    'uploaded_by' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                    ],
                ],
            ],
            'total_size',
            'total_size_formatted',
        ])
        ->assertJsonCount(2, 'attachments');

    $v = (int)Cache::get('attachments:version', 1);
    $filters = ['order_id' => $this->order->id];
    ksort($filters);
    $signature = md5(json_encode($filters));
    expect(Cache::has("attachments:index:v{$v}:f:{$signature}"))->toBeTrue();

    $second = $this->getJson("/api/v1/orders/{$this->order->uuid}/attachments");
    $second->assertOk()
        ->assertHeader('X-Cache', 'HIT')
        ->assertJsonCount(2, 'attachments');
});
it('checks cache invalidation on attachment creation', function () {
    $this->actingAs($this->employee);

    $v1 = (int)Cache::get('attachments:version', 0);

    $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
        'file' => $file,
        'name' => 'Important Document',
        'description' => 'Contract document for this order',
    ]);

    $response->assertCreated();

    $v2 = (int)Cache::get('attachments:version', 0);
    expect($v2)->toBe($v1 + 1, 'Attachments cache version should bump on create');
});
it('checks cache invalidation on attachment deletion', function () {
    $this->actingAs($this->employee);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => 'orders/test-file.pdf',
    ]);

    Storage::disk('public')->put('orders/test-file.pdf', 'test content');

    $v1 = (int)Cache::get('attachments:version', 0);

    $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

    $response->assertOk();

    $v2 = (int)Cache::get('attachments:version', 0);
    expect($v2)->toBe($v1 + 1, 'Attachments cache version should bump on delete');
});
it('checks customer can view own order attachments', function () {
    $this->actingAs($this->customer);

    $response = $this->getJson("/api/v1/orders/{$this->order->uuid}/attachments");

    $response->assertOk();
});
it('checks customer cannot view other order attachments', function () {
    $this->actingAs($this->customer);

    $response = $this->getJson("/api/v1/orders/{$this->otherOrder->uuid}/attachments");

    $response->assertForbidden();
});
it('checks upload attachment to order', function () {
    $this->actingAs($this->employee);

    $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
        'file' => $file,
        'name' => 'Important Document',
        'description' => 'Contract document for this order',
    ]);

    $response->assertCreated()
        ->assertJson([
            'message' => 'File uploaded successfully.',
            'attachment' => [
                'file_name' => 'Important Document',
                'mime_type' => 'application/pdf',
            ],
        ]);

    // Check file was stored
    Storage::disk('public')->assertExists("orders/{$this->order->uuid}/" . $file->hashName());

    // Check database record
    $this->assertDatabaseHas('attachments', [
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'Important Document',
    ]);

    // Check history was created (description is a computed attribute, not a DB field)
    $history = OrderHistory::where('order_id', $this->order->id)
        ->where('field_changed', 'attachments')
        ->latest()
        ->first();

    expect($history)->not->toBeNull();
    $this->assertStringContainsString('Important Document', $history->description);
});
it('checks upload multiple attachments to order', function () {
    $this->actingAs($this->employee);

    $file1 = UploadedFile::fake()->create('doc1.pdf', 500, 'application/pdf');
    $file2 = UploadedFile::fake()->image('image1.jpg', 100, 100);

    $response = $this->post("/api/v1/orders/{$this->order->uuid}/attachments", [
        'files' => [$file1, $file2],
    ]);

    $response->assertCreated()
        ->assertJson([
            'message' => 'Files uploaded successfully.',
        ])
        ->assertJsonCount(2, 'attachments');

    // Check files stored
    Storage::disk('public')->assertExists("orders/{$this->order->uuid}/" . $file1->hashName());
    Storage::disk('public')->assertExists("orders/{$this->order->uuid}/" . $file2->hashName());

    // Check DB records exist
    $this->assertDatabaseHas('attachments', [
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'doc1.pdf',
    ]);
    $this->assertDatabaseHas('attachments', [
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_name' => 'image1.jpg',
    ]);

    // Check two history entries created for attachments
    $histories = OrderHistory::where('order_id', $this->order->id)
        ->where('field_changed', 'attachments')
        ->latest()
        ->take(2)
        ->get();
    expect($histories)->toHaveCount(2);
});
it('checks upload validates file type', function () {
    $this->actingAs($this->employee);

    $file = UploadedFile::fake()->create('script.exe', 1000);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
        'file' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});
it('checks upload validates file size', function () {
    $this->actingAs($this->employee);

    // Create a file larger than 10MB
    $file = UploadedFile::fake()->create('large.pdf', 11000);

    // 11MB
    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
        'file' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});
it('checks customer cannot upload attachments', function () {
    $this->actingAs($this->customer);

    $file = UploadedFile::fake()->create('document.pdf', 1000);

    $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
        'file' => $file,
    ]);

    $response->assertForbidden();
});
it('checks download attachment', function () {
    $this->actingAs($this->employee);

    // Create an attachment with a real file
    $filePath = 'orders/test-file.pdf';
    Storage::disk('public')->put($filePath, 'test content');

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => $filePath,
        'file_name' => 'test-document.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->get("/api/v1/attachments/{$attachment->uuid}/download");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=test-document.pdf');
});
it('checks download attachment requires authorization', function () {
    $this->actingAs($this->customer);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->otherOrder->id,
        'file_path' => 'orders/test-file.pdf',
    ]);

    $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/download");

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Unauthorized to download this attachment.',
        ]);
});
it('checks download non existent file', function () {
    $this->actingAs($this->employee);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => 'orders/non-existent.pdf',
    ]);

    $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/download");

    $response->assertNotFound()
        ->assertJson([
            'message' => 'File not found.',
        ]);
});
it('checks preview image attachment', function () {
    $this->actingAs($this->employee);

    // Create an image attachment
    $filePath = 'orders/test-image.jpg';
    Storage::disk('public')->put($filePath, 'fake image content');

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => $filePath,
        'file_name' => 'test-image.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->get("/api/v1/attachments/{$attachment->uuid}/preview");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Disposition', 'inline; filename="test-image.jpg"');
});
it('checks preview pdf attachment', function () {
    $this->actingAs($this->employee);

    // Create a PDF attachment
    $filePath = 'orders/test-document.pdf';
    Storage::disk('public')->put($filePath, 'fake pdf content');

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => $filePath,
        'file_name' => 'test-document.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->get("/api/v1/attachments/{$attachment->uuid}/preview");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="test-document.pdf"');
});
it('checks cannot preview non previewable files', function () {
    $this->actingAs($this->employee);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => 'orders/test.zip',
        'mime_type' => 'application/zip',
    ]);

    $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/preview");

    $response->assertBadRequest()
        ->assertJson([
            'message' => 'This file type cannot be previewed.',
        ]);
});
it('checks preview requires authorization', function () {
    $this->actingAs($this->customer);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->otherOrder->id,
        'file_path' => 'orders/test.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/preview");

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Unauthorized to preview this attachment.',
        ]);
});
it('checks delete attachment', function () {
    $this->actingAs($this->employee);

    // Create an attachment
    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
        'file_path' => 'orders/test-file.pdf',
    ]);

    // Put a fake file in storage
    Storage::disk('public')->put('orders/test-file.pdf', 'test content');

    $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

    $response->assertOk()
        ->assertJson([
            'message' => 'Attachment deleted successfully.',
        ]);

    // Check file was deleted
    Storage::disk('public')->assertMissing('orders/test-file.pdf');

    // Check database record was deleted
    $this->assertDatabaseMissing('attachments', [
        'id' => $attachment->id,
    ]);

    // Check history was created (description is a computed attribute, not a DB field)
    $history = OrderHistory::where('order_id', $this->order->id)
        ->where('field_changed', 'attachments')
        ->latest()
        ->first();

    expect($history)->not->toBeNull();
    $this->assertStringContainsString('Attachments removed', $history->description);
});
it('checks cannot delete non order attachment', function () {
    $this->actingAs($this->employee);

    // Create an attachment for different type
    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order_history',
        'attachable_id' => OrderHistory::factory()->create()->id,
    ]);

    $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

    $response->assertForbidden()
        ->assertJson([
            'message' => 'This attachment does not belong to an order.',
        ]);
});
it('checks attachment deletion requires authorization', function () {
    $this->actingAs($this->customer);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->order->id,
    ]);

    $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

    $response->assertForbidden();
});
it('checks employee cannot delete attachment from unassigned order', function () {
    $this->actingAs($this->employee);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->otherOrder->id,
    ]);

    $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Unauthorized to delete this attachment.',
        ]);
});
it('checks admin can delete any attachment', function () {
    $this->actingAs($this->admin);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'order',
        'attachable_id' => $this->otherOrder->id,
        'file_path' => 'orders/test-file.pdf',
    ]);

    Storage::disk('public')->put('orders/test-file.pdf', 'test content');

    $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

    $response->assertOk()
        ->assertJson([
            'message' => 'Attachment deleted successfully.',
        ]);
});
