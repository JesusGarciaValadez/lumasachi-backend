<?php

namespace Modules\Lumasachi\tests\Feature\app\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Factories\Sequence;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test order and related models creation.
     */
    public function test_order_creation_with_relationships()
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $assignee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
            'assigned_to' => $assignee->id,
        ]);

        $this->assertInstanceOf(User::class, $order->customer);
        $this->assertEquals($customer->id, $order->customer->id);

        $this->assertInstanceOf(User::class, $order->createdBy);
        $this->assertEquals($creator->id, $order->createdBy->id);

        $this->assertInstanceOf(User::class, $order->updatedBy);
        $this->assertEquals($updater->id, $order->updatedBy->id);

        $this->assertInstanceOf(User::class, $order->assignedTo);
        $this->assertEquals($assignee->id, $order->assignedTo->id);
    }

    /**
     * Test order status transitions.
     */
    public function test_order_status_transitions()
    {
        $order = Order::factory()->create(['status' => Order::STATUS_OPEN]);
        $order->update(['status' => Order::STATUS_IN_PROGRESS]);

        $this->assertEquals(Order::STATUS_IN_PROGRESS, $order->status);

        $order->update(['status' => Order::STATUS_DELIVERED]);

        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
    }

    /**
     * Test order has attachments.
     */
    public function test_order_attachments()
    {
        $order = Order::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $attachment = $order->attach($file, $order->created_by);

        $this->assertCount(1, $order->attachments);
        $this->assertEquals($file->getClientOriginalName(), $attachment->file_name);
        $this->assertEquals('document.pdf', $attachment->file_name);
    }

    /**
     * Test order can be attached with multiple files.
     */
    public function test_order_multiple_attachments()
    {
        $order = Order::factory()->create();

        $files = [
            UploadedFile::fake()->create('document1.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('document2.pdf', 200, 'application/pdf')
        ];

        foreach ($files as $file) {
            $order->attach($file, $order->created_by);
        }

        $this->assertCount(2, $order->attachments);
    }

    /**
     * Test order history records.
     */
    public function test_order_history_records()
    {
        $order = Order::factory()->create();

        $history = OrderHistory::factory()->count(3)->state(new Sequence(
            ['order_id' => $order->id]
        ))->create();

        $this->assertCount(3, $order->orderHistories);
    }

    /**
     * Test customer relationship constraint.
     */
    public function test_customer_relationship_only_returns_customer_role_users()
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        
        // Create order with employee ID as customer_id (shouldn't work with relationship)
        $order = Order::factory()->create(['customer_id' => $employee->id]);
        
        // The customer relationship should return null because employee is not a customer
        $this->assertNull($order->customer);
        
        // Create order with actual customer
        $order2 = Order::factory()->create(['customer_id' => $customer->id]);
        $this->assertNotNull($order2->customer);
        $this->assertEquals($customer->id, $order2->customer->id);
    }

    /**
     * Test assigned to relationship constraint.
     */
    public function test_assigned_to_relationship_only_returns_employee_role_users()
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        
        // Create order assigned to customer (shouldn't work with relationship)
        $order = Order::factory()->create(['assigned_to' => $customer->id]);
        
        // The assignedTo relationship should return null because customer is not an employee
        $this->assertNull($order->assignedTo);
        
        // Create order assigned to actual employee
        $order2 = Order::factory()->create(['assigned_to' => $employee->id]);
        $this->assertNotNull($order2->assignedTo);
        $this->assertEquals($employee->id, $order2->assignedTo->id);
    }

    /**
     * Test order date casting.
     */
    public function test_order_date_casting()
    {
        $estimatedDate = now()->addDays(7);
        $completedDate = now()->subDays(2);
        
        $order = Order::factory()->create([
            'estimated_completion' => $estimatedDate,
            'actual_completion' => $completedDate,
        ]);

        // Test that dates are cast to Carbon instances
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $order->estimated_completion);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $order->actual_completion);
        
        // Test date values
        $this->assertEquals($estimatedDate->format('Y-m-d'), $order->estimated_completion->format('Y-m-d'));
        $this->assertEquals($completedDate->format('Y-m-d'), $order->actual_completion->format('Y-m-d'));
    }

    /**
     * Test order factory states.
     */
    public function test_order_factory_states()
    {
        // Test completed state
        $completedOrder = Order::factory()->completed()->create();
        $this->assertEquals(Order::STATUS_DELIVERED, $completedOrder->status);
        $this->assertNotNull($completedOrder->actual_completion);
        
        // Test open state
        $openOrder = Order::factory()->open()->create();
        $this->assertEquals(Order::STATUS_OPEN, $openOrder->status);
        $this->assertNull($openOrder->actual_completion);
    }

    /**
     * Test has attachments trait methods.
     */
    public function test_has_attachments_trait_methods()
    {
        $order = Order::factory()->create();
        $user = $order->createdBy;
        
        // Test hasAttachments method
        $this->assertFalse($order->hasAttachments());
        
        // Add attachments
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $imageFile = UploadedFile::fake()->create('image.jpg', 200, 'image/jpeg');
        
        $order->attach($pdfFile, $user->id);
        $order->attach($imageFile, $user->id);
        
        // Test hasAttachments after adding files
        $this->assertTrue($order->hasAttachments());
        
        // Test getAttachmentsByType
        $pdfAttachments = $order->getAttachmentsByType('application/pdf');
        $this->assertCount(1, $pdfAttachments);
        
        // Test partial mime type
        $imageAttachments = $order->getAttachmentsByType('image');
        $this->assertCount(1, $imageAttachments);
        
        // Test getImageAttachments
        $images = $order->getImageAttachments();
        $this->assertCount(1, $images);
        
        // Test getDocumentAttachments
        $documents = $order->getDocumentAttachments();
        $this->assertCount(1, $documents);
        
        // Test getTotalAttachmentsSize
        $totalSize = $order->getTotalAttachmentsSize();
        $this->assertEquals(300 * 1024, $totalSize); // 300 KB (100 + 200 KB)
        
        // Test getTotalAttachmentsSizeFormatted
        $formattedSize = $order->getTotalAttachmentsSizeFormatted();
        $this->assertEquals('300 KB', $formattedSize);
    }

    /**
     * Test detaching attachments.
     */
    public function test_detaching_attachments()
    {
        $order = Order::factory()->create();
        $user = $order->createdBy;
        
        // Add attachments
        $file1 = UploadedFile::fake()->create('file1.pdf', 100);
        $file2 = UploadedFile::fake()->create('file2.pdf', 200);
        
        $attachment1 = $order->attach($file1, $user->id);
        $attachment2 = $order->attach($file2, $user->id);
        
        $this->assertCount(2, $order->attachments);
        
        // Test detaching single attachment
        $result = $order->detach($attachment1->id);
        $this->assertTrue($result);
        $this->assertCount(1, $order->fresh()->attachments);
        
        // Test detaching non-existent attachment
        $result = $order->detach('00000000-0000-0000-0000-000000000000');
        $this->assertFalse($result);
        
        // Test detachAll
        $order->attach($file1, $user->id); // Add another attachment
        $order = $order->fresh(); // Refresh to get all attachments
        $this->assertCount(2, $order->attachments);
        
        $deletedCount = $order->detachAll();
        $this->assertEquals(2, $deletedCount);
        $this->assertCount(0, $order->fresh()->attachments);
    }

    /**
     * Test order UUID primary key.
     */
    public function test_order_uses_uuid_as_primary_key()
    {
        $order = Order::factory()->create();
        
        // Check that ID is a valid UUID (Laravel 11 uses UUID v7)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $order->id
        );
    }

    /**
     * Test mass assignment protection.
     */
    public function test_mass_assignment_protection()
    {
        $fillableFields = [
            'customer_id',
            'title',
            'description',
            'status',
            'priority',
            'category',
            'estimated_completion',
            'actual_completion',
            'notes',
            'created_by',
            'updated_by',
            'assigned_to'
        ];
        
        $order = new Order();
        $this->assertEquals($fillableFields, $order->getFillable());
    }

    /**
     * Test order priority values.
     */
    public function test_order_priority_values()
    {
        $priorities = [
            Order::PRIORITY_LOW,
            Order::PRIORITY_NORMAL,
            Order::PRIORITY_HIGH,
            Order::PRIORITY_URGENT
        ];
        
        foreach ($priorities as $priority) {
            $order = Order::factory()->create(['priority' => $priority]);
            $this->assertEquals($priority, $order->priority);
        }
    }

    /**
     * Test order status values.
     */
    public function test_order_status_values()
    {
        $statuses = [
            Order::STATUS_OPEN,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_READY_FOR_DELIVERY,
            Order::STATUS_DELIVERED,
            Order::STATUS_PAID,
            Order::STATUS_RETURNED,
            Order::STATUS_NOT_PAID,
            Order::STATUS_CANCELLED
        ];
        
        foreach ($statuses as $status) {
            $order = Order::factory()->create(['status' => $status]);
            $this->assertEquals($status, $order->status);
        }
    }

    /**
     * Test order with null optional fields.
     */
    public function test_order_with_null_optional_fields()
    {
        $order = Order::factory()->create([
            'actual_completion' => null,
            'notes' => null,
            'assigned_to' => null
        ]);
        
        $this->assertNull($order->actual_completion);
        $this->assertNull($order->notes);
        $this->assertNull($order->assigned_to);
        $this->assertNull($order->assignedTo); // relationship should also be null
    }
}
