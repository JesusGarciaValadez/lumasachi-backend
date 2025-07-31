<?php

namespace Modules\Lumasachi\tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Tests\TestCase;

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
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $this->employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        
        // Create test orders
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->employee->id,
            'assigned_to' => $this->employee->id,
            'status' => Order::STATUS_OPEN
        ]);
        
        $this->otherOrder = Order::factory()->create([
            'customer_id' => User::factory()->create(['role' => UserRole::CUSTOMER])->id,
            'created_by' => $this->employee2->id,
            'assigned_to' => $this->employee2->id,
            'status' => Order::STATUS_OPEN
        ]);
    }

    /**
     * Test viewing order attachments
     */
    public function test_view_order_attachments()
    {
        $this->actingAs($this->employee);

        // Create some attachments
        Attachment::factory()->count(2)->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'uploaded_by' => $this->employee->id
        ]);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/attachments");

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
    public function test_customer_can_view_own_order_attachments()
    {
        $this->actingAs($this->customer);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/attachments");

        $response->assertOk();
    }

    /**
     * Test customer cannot view attachments for other orders
     */
    public function test_customer_cannot_view_other_order_attachments()
    {
        $this->actingAs($this->customer);

        $response = $this->getJson("/api/v1/orders/{$this->otherOrder->id}/attachments");

        $response->assertForbidden();
    }

    /**
     * Test uploading attachment to order
     */
    public function test_upload_attachment_to_order()
    {
        $this->actingAs($this->employee);

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/attachments", [
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
        Storage::disk('public')->assertExists("orders/{$this->order->id}/" . $file->hashName());

        // Check database record
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_name' => 'Important Document'
        ]);

        // Check history
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $this->order->id,
            'description' => 'Attachment uploaded'
        ]);
    }

    /**
     * Test upload validates file type
     */
    public function test_upload_validates_file_type()
    {
        $this->actingAs($this->employee);

        $file = UploadedFile::fake()->create('script.exe', 1000);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/attachments", [
            'file' => $file
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test upload validates file size
     */
    public function test_upload_validates_file_size()
    {
        $this->actingAs($this->employee);

        // Create a file larger than 10MB
        $file = UploadedFile::fake()->create('large.pdf', 11000); // 11MB

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/attachments", [
            'file' => $file
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test customer cannot upload attachments
     */
    public function test_customer_cannot_upload_attachments()
    {
        $this->actingAs($this->customer);

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/attachments", [
            'file' => $file
        ]);

        $response->assertForbidden();
    }

    /**
     * Test downloading attachment
     */
    public function test_download_attachment()
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

        $response = $this->get("/api/v1/attachments/{$attachment->id}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=test-document.pdf');
    }

    /**
     * Test download attachment requires authorization
     */
    public function test_download_attachment_requires_authorization()
    {
        $this->actingAs($this->customer);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id,
            'file_path' => 'orders/test-file.pdf'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->id}/download");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized to download this attachment.'
            ]);
    }

    /**
     * Test download non-existent file
     */
    public function test_download_non_existent_file()
    {
        $this->actingAs($this->employee);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => 'orders/non-existent.pdf'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->id}/download");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'File not found.'
            ]);
    }

    /**
     * Test previewing image attachment
     */
    public function test_preview_image_attachment()
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

        $response = $this->get("/api/v1/attachments/{$attachment->id}/preview");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename="test-image.jpg"');
    }

    /**
     * Test previewing PDF attachment
     */
    public function test_preview_pdf_attachment()
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

        $response = $this->get("/api/v1/attachments/{$attachment->id}/preview");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="test-document.pdf"');
    }

    /**
     * Test cannot preview non-previewable file types
     */
    public function test_cannot_preview_non_previewable_files()
    {
        $this->actingAs($this->employee);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id,
            'file_path' => 'orders/test.zip',
            'mime_type' => 'application/zip'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->id}/preview");

        $response->assertBadRequest()
            ->assertJson([
                'message' => 'This file type cannot be previewed.'
            ]);
    }

    /**
     * Test preview requires authorization
     */
    public function test_preview_requires_authorization()
    {
        $this->actingAs($this->customer);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id,
            'file_path' => 'orders/test.pdf',
            'mime_type' => 'application/pdf'
        ]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->id}/preview");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized to preview this attachment.'
            ]);
    }

    /**
     * Test deleting attachment
     */
    public function test_delete_attachment()
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

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->id}");

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

        // Check history
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $this->order->id,
            'description' => 'Attachment deleted'
        ]);
    }

    /**
     * Test cannot delete attachment from different type
     */
    public function test_cannot_delete_non_order_attachment()
    {
        $this->actingAs($this->employee);

        // Create an attachment for different type
        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order_history',
            'attachable_id' => OrderHistory::factory()->create()->id
        ]);

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->id}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'This attachment does not belong to an order.'
            ]);
    }

    /**
     * Test attachment deletion requires authorization
     */
    public function test_attachment_deletion_requires_authorization()
    {
        $this->actingAs($this->customer);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->order->id
        ]);

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->id}");

        $response->assertForbidden();
    }

    /**
     * Test employee cannot delete attachment from order they don't manage
     */
    public function test_employee_cannot_delete_attachment_from_unassigned_order()
    {
        $this->actingAs($this->employee);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id
        ]);

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->id}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized to delete this attachment.'
            ]);
    }

    /**
     * Test admin can delete any attachment
     */
    public function test_admin_can_delete_any_attachment()
    {
        $this->actingAs($this->admin);

        $attachment = Attachment::factory()->create([
            'attachable_type' => 'order',
            'attachable_id' => $this->otherOrder->id,
            'file_path' => 'orders/test-file.pdf'
        ]);

        Storage::disk('public')->put('orders/test-file.pdf', 'test content');

        $response = $this->deleteJson("/api/v1/attachments/{$attachment->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Attachment deleted successfully.'
            ]);
    }
}
