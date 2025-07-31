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

class OrderAdvancedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $employee;
    protected $employee2;
    protected $customer;
    protected $order;

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
        
        // Create a test order
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->employee->id,
            'assigned_to' => $this->employee->id,
            'status' => Order::STATUS_OPEN
        ]);
    }

    /**
     * Test updating order status with valid transition
     */
    public function test_update_status_with_valid_transition()
    {
        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => Order::STATUS_IN_PROGRESS,
            'notes' => 'Starting work on this order'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => Order::STATUS_IN_PROGRESS
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => Order::STATUS_IN_PROGRESS
        ]);

        // Check history was created
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $this->order->id,
            'status_from' => Order::STATUS_OPEN,
            'status_to' => Order::STATUS_IN_PROGRESS,
            'description' => 'Status changed'
        ]);
    }

    /**
     * Test updating order status with invalid transition
     */
    public function test_update_status_with_invalid_transition()
    {
        $this->actingAs($this->employee);

        // Set order to paid status
        $this->order->update(['status' => Order::STATUS_PAID]);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => Order::STATUS_IN_PROGRESS
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test status update requires valid status
     */
    public function test_update_status_validates_status()
    {
        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => 'InvalidStatus'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test assigning order to employee
     */
    public function test_assign_order_to_employee()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id,
            'notes' => 'Reassigning to more experienced employee'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order assigned successfully.',
                'order' => [
                    'assigned_to' => [
                        'id' => $this->employee2->id
                    ]
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'assigned_to' => $this->employee2->id
        ]);

        // Check history
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $this->order->id,
            'description' => 'Order reassigned'
        ]);
    }

    /**
     * Test cannot assign order to customer
     */
    public function test_cannot_assign_order_to_customer()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->customer->id
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    }

    /**
     * Test assignment requires authorization
     */
    public function test_assignment_requires_authorization()
    {
        $this->actingAs($this->customer);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id
        ]);

        $response->assertForbidden();
    }

    /**
     * Test viewing order history
     */
    public function test_view_order_history()
    {
        $this->actingAs($this->employee);

        // Create some history entries
        OrderHistory::factory()->count(3)->create([
            'order_id' => $this->order->id
        ]);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/history");

        $response->assertOk()
            ->assertJsonStructure([
                'order_id',
                'history' => [
                    '*' => [
                        'id',
                        'order_id',
                        'status_from',
                        'status_to',
                        'priority_from',
                        'priority_to',
                        'description',
                        'notes',
                        'created_by',
                        'created_at'
                    ]
                ]
            ])
            ->assertJsonCount(3, 'history');
    }

    /**
     * Test history is returned in descending order
     */
    public function test_history_is_ordered_by_newest_first()
    {
        $this->actingAs($this->employee);

        // Create history entries with specific timestamps
        $oldHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_at' => now()->subDays(2)
        ]);

        $newHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_at' => now()
        ]);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/history");

        $response->assertOk();
        
        $history = $response->json('history');
        $this->assertEquals($newHistory->id, $history[0]['id']);
        $this->assertEquals($oldHistory->id, $history[1]['id']);
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
            'attachable_id' => $this->order->id
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
                        'extension'
                    ]
                ],
                'total_size',
                'total_size_formatted'
            ])
            ->assertJsonCount(2, 'attachments');
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
}
