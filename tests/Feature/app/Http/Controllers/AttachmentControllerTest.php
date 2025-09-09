<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $employee;
    protected $employee2;
    protected $customer;
    protected $order;
    protected $otherOrder;

    protected function setUp(): void
    {
        parent::setUp();

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
            'status' => OrderStatus::OPEN->value
        ]);

        $this->otherOrder = Order::factory()->createQuietly([
            'uuid' => Str::uuid7()->toString(),
            'customer_id' => User::factory()->create(['role' => UserRole::CUSTOMER->value])->id,
            'created_by' => $this->employee2->id,
            'assigned_to' => $this->employee2->id,
            'status' => OrderStatus::OPEN->value
        ]);
    }

    /**
     * Test viewing order attachments
     */
    #[Test]
    public function it_checks_view_order_attachments()
    {
        $this->actingAs($this->employee);

        // Create some attachments
        Attachment::factory()->count(2)->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'uploaded_by' => $this->employee->id
        ]);

        $response = $this->getJson("/api/v1/orders/{$this->order->uuid}/attachments");

        $response->assertOk()
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
                            'email'
                        ]
                    ]
                ],
                'total_size',
                'total_size_formatted'
            ])
            ->assertJsonCount(2, 'attachments');
    }

    /**
     * Test customer can view attachments for their order
     */
    #[Test]
    public function it_checks_customer_can_view_own_order_attachments()
    {
        $this->actingAs($this->customer);

        $response = $this->getJson("/api/v1/orders/{$this->order->uuid}/attachments");

        $response->assertOk();
    }

    /**
     * Test customer cannot view attachments for other orders
     */
    #[Test]
    public function it_checks_customer_cannot_view_other_order_attachments()
    {
        $this->actingAs($this->customer);

        $response = $this->getJson("/api/v1/orders/{$this->otherOrder->uuid}/attachments");

        $response->assertForbidden();
    }

    /**
     * Test uploading attachment to order (single file)
     */
    #[Test]
    public function it_checks_upload_attachment_to_order()
    {
        $this->actingAs($this->employee);

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
            'file' => $file,
            'name' => 'Important Document',
            'description' => 'Contract document for this order'
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'File uploaded successfully.',
                'attachment' => [
                    'file_name' => 'Important Document',
                    'mime_type' => 'application/pdf'
                ]
            ]);

        // Check file was stored
        Storage::disk('public')->assertExists("orders/{$this->order->uuid}/" . $file->hashName());

        // Check database record
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'Important Document'
        ]);

        // Check history was created (description is a computed attribute, not a DB field)
        $history = OrderHistory::where('order_id', $this->order->id)
            ->where('field_changed', 'attachments')
            ->latest()
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('Important Document', $history->description);
    }

    /**
     * Test uploading multiple attachments to order
     */
    #[Test]
    public function it_checks_upload_multiple_attachments_to_order()
    {
        $this->actingAs($this->employee);

        $file1 = UploadedFile::fake()->create('doc1.pdf', 500, 'application/pdf');
        $file2 = UploadedFile::fake()->image('image1.jpg', 100, 100);

        $response = $this->post("/api/v1/orders/{$this->order->uuid}/attachments", [
            'files' => [$file1, $file2],
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Files uploaded successfully.'
            ])
            ->assertJsonCount(2, 'attachments');

        // Check files stored
        Storage::disk('public')->assertExists("orders/{$this->order->uuid}/" . $file1->hashName());
        Storage::disk('public')->assertExists("orders/{$this->order->uuid}/" . $file2->hashName());

        // Check DB records exist
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'doc1.pdf'
        ]);
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'image1.jpg'
        ]);

        // Check two history entries created for attachments
        $histories = OrderHistory::where('order_id', $this->order->id)
            ->where('field_changed', 'attachments')
            ->latest()
            ->take(2)
            ->get();
        $this->assertCount(2, $histories);
    }

    /**
     * Test upload validates file type
     */
    #[Test]
    public function it_checks_upload_validates_file_type()
    {
        $this->actingAs($this->employee);

        $file = UploadedFile::fake()->create('script.exe', 1000);

        $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
            'file' => $file
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test upload validates file size
     */
    #[Test]
    public function it_checks_upload_validates_file_size()
    {
        $this->actingAs($this->employee);

        // Create a file larger than 10MB
        $file = UploadedFile::fake()->create('large.pdf', 11000); // 11MB

        $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
            'file' => $file
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test customer cannot upload attachments
     */
    #[Test]
    public function it_checks_customer_cannot_upload_attachments()
    {
        $this->actingAs($this->customer);

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson("/api/v1/orders/{$this->order->uuid}/attachments", [
            'file' => $file
        ]);

        $response->assertForbidden();
    }

    /**
     * Test downloading attachment
     */
    #[Test]
    public function it_checks_download_attachment()
    {
        $this->actingAs($this->employee);

        // Create an attachment with a real file
        $filePath = 'orders/test-file.pdf';
        Storage::disk('public')->put($filePath, 'test content');

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => $filePath,
            'file_name' => 'test-document.pdf',
            'mime_type' => 'application/pdf'
        ]);

        $response = $this->get("/api/v1/attachments/{$attachment->uuid}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=test-document.pdf');
    }

    /**
     * Test download attachment requires authorization
     */
    #[Test]
    public function it_checks_download_attachment_requires_authorization()
    {
        $this->actingAs($this->customer);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id,
            'file_path' => 'orders/test-file.pdf'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/download");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized to download this attachment.'
            ]);
    }

    /**
     * Test download non-existent file
     */
    #[Test]
    public function it_checks_download_non_existent_file()
    {
        $this->actingAs($this->employee);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => 'orders/non-existent.pdf'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/download");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'File not found.'
            ]);
    }

    /**
     * Test previewing image attachment
     */
    #[Test]
    public function it_checks_preview_image_attachment()
    {
        $this->actingAs($this->employee);

        // Create an image attachment
        $filePath = 'orders/test-image.jpg';
        Storage::disk('public')->put($filePath, 'fake image content');

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => $filePath,
            'file_name' => 'test-image.jpg',
            'mime_type' => 'image/jpeg'
        ]);

        $response = $this->get("/api/v1/attachments/{$attachment->uuid}/preview");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename="test-image.jpg"');
    }

    /**
     * Test previewing PDF attachment
     */
    #[Test]
    public function it_checks_preview_pdf_attachment()
    {
        $this->actingAs($this->employee);

        // Create a PDF attachment
        $filePath = 'orders/test-document.pdf';
        Storage::disk('public')->put($filePath, 'fake pdf content');

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => $filePath,
            'file_name' => 'test-document.pdf',
            'mime_type' => 'application/pdf'
        ]);

        $response = $this->get("/api/v1/attachments/{$attachment->uuid}/preview");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="test-document.pdf"');
    }

    /**
     * Test cannot preview non-previewable file types
     */
    #[Test]
    public function it_checks_cannot_preview_non_previewable_files()
    {
        $this->actingAs($this->employee);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => 'orders/test.zip',
            'mime_type' => 'application/zip'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/preview");

        $response->assertBadRequest()
            ->assertJson([
                'message' => 'This file type cannot be previewed.'
            ]);
    }

    /**
     * Test preview requires authorization
     */
    #[Test]
    public function it_checks_preview_requires_authorization()
    {
        $this->actingAs($this->customer);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id,
            'file_path' => 'orders/test.pdf',
            'mime_type' => 'application/pdf'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->uuid}/preview");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized to preview this attachment.'
            ]);
    }

    /**
     * Test deleting attachment
     */
    #[Test]
    public function it_checks_delete_attachment()
    {
        $this->actingAs($this->employee);

        // Create an attachment
        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => 'orders/test-file.pdf'
        ]);

        // Put a fake file in storage
        Storage::disk('public')->put('orders/test-file.pdf', 'test content');

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Attachment deleted successfully.'
            ]);

        // Check file was deleted
        Storage::disk('public')->assertMissing('orders/test-file.pdf');

        // Check database record was deleted
        $this->assertDatabaseMissing('attachments', [
            'id' => $attachment->id
        ]);

        // Check history was created (description is a computed attribute, not a DB field)
        $history = OrderHistory::where('order_id', $this->order->id)
            ->where('field_changed', 'attachments')
            ->latest()
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('Attachments removed', $history->description);
    }

    /**
     * Test cannot delete attachment from different type
     */
    #[Test]
    public function it_checks_cannot_delete_non_order_attachment()
    {
        $this->actingAs($this->employee);

        // Create an attachment for different type
        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order_history',
            'attachable_id' => OrderHistory::factory()->create()->id
        ]);

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'This attachment does not belong to an order.'
            ]);
    }

    /**
     * Test attachment deletion requires authorization
     */
    #[Test]
    public function it_checks_attachment_deletion_requires_authorization()
    {
        $this->actingAs($this->customer);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id
        ]);

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

        $response->assertForbidden();
    }

    /**
     * Test employee cannot delete attachment from order they don't manage
     */
    #[Test]
    public function it_checks_employee_cannot_delete_attachment_from_unassigned_order()
    {
        $this->actingAs($this->employee);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id
        ]);

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized to delete this attachment.'
            ]);
    }

    /**
     * Test admin can delete any attachment
     */
    #[Test]
    public function it_checks_admin_can_delete_any_attachment()
    {
        $this->actingAs($this->admin);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id,
            'file_path' => 'orders/test-file.pdf'
        ]);

        Storage::disk('public')->put('orders/test-file.pdf', 'test content');

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->uuid}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Attachment deleted successfully.'
            ]);
    }

    protected function tearDown(): void
    {
        // Clean up any test files created during tests
        Storage::fake('public');
        parent::tearDown();
    }
}
