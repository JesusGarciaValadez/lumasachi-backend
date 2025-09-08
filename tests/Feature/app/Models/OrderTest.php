<?php

namespace Tests\Feature\app\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Factories\Sequence;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Category;

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
    #[Test]
    public function it_checks_order_creation_with_relationships()
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $assignee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $categories = Category::factory()->count(2)->create();

        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
            'assigned_to' => $assignee->id,
        ]);
        $order->categories()->attach($categories->pluck('id'));

        $this->assertInstanceOf(User::class, $order->customer);
        $this->assertEquals($customer->id, $order->customer->id);

        $this->assertInstanceOf(User::class, $order->createdBy);
        $this->assertEquals($creator->id, $order->createdBy->id);

        $this->assertInstanceOf(User::class, $order->updatedBy);
        $this->assertEquals($updater->id, $order->updatedBy->id);

        $this->assertInstanceOf(User::class, $order->assignedTo);
        $this->assertEquals($assignee->id, $order->assignedTo->id);

        $this->assertCount(2, $order->categories);
        $this->assertTrue($order->categories->contains($categories->first()));
        $this->assertTrue($order->categories->contains($categories->last()));
    }

    /**
     * Test order can have multiple categories.
     */
    #[Test]
    public function it_checks_order_can_have_multiple_categories(): void
    {
        $order = Order::factory()->createQuietly();
        $categories = Category::factory()->count(3)->create();

        $order->categories()->attach($categories->pluck('id'));

        $this->assertCount(3, $order->categories);
        foreach ($categories as $category) {
            $this->assertTrue($order->categories->contains($category));
        }
    }

    /**
     * Test detaching categories from order.
     */
    #[Test]
    public function it_checks_detaching_categories_from_order(): void
    {
        $order = Order::factory()->createQuietly();
        $categories = Category::factory()->count(3)->create();

        $order->categories()->attach($categories->pluck('id'));
        $this->assertCount(3, $order->fresh()->categories);

        $order->categories()->detach($categories->first()->id);
        $this->assertCount(2, $order->fresh()->categories);

        $order->categories()->detach(); // Detach all
        $this->assertCount(0, $order->fresh()->categories);
    }

    /**
     * Test order status transitions.
     */
    #[Test]
    public function it_checks_order_status_transitions()
    {
        $order = Order::factory()->createQuietly(['status' => OrderStatus::OPEN->value]);
        $order->update(['status' => OrderStatus::IN_PROGRESS->value]);

        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $order->status->value);

        $order->update(['status' => OrderStatus::DELIVERED->value]);

        $this->assertEquals(OrderStatus::DELIVERED->value, $order->status->value);
    }

    /**
     * Test order has attachments.
     */
    #[Test]
    public function it_checks_order_attachments()
    {
        $order = Order::factory()->createQuietly();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $attachment = $order->attach($file, $order->created_by);

        $this->assertCount(1, $order->attachments);
        $this->assertEquals($file->getClientOriginalName(), $attachment->file_name);
        $this->assertEquals('document.pdf', $attachment->file_name);
    }

    /**
     * Test order can be attached with multiple files.
     */
    #[Test]
    public function it_checks_order_multiple_attachments()
    {
        $order = Order::factory()->createQuietly();

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
    #[Test]
    public function it_checks_order_history_records()
    {
        $order = Order::factory()->createQuietly();

        $history = OrderHistory::factory()->count(3)->state(new Sequence(
            ['order_id' => $order->id]
        ))->create();

        $this->assertCount(3, $order->orderHistories);
    }

    /**
     * Test customer relationship constraint.
     */
    #[Test]
    public function it_checks_customer_relationship_only_returns_customer_role_users()
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        // Create order with employee ID as customer_id (shouldn't work with relationship)
        $order = Order::factory()->createQuietly([
            'customer_id' => $employee->id,
            'assigned_to' => User::factory()->create()->id,
        ]);

        // The customer relationship should return null because employee is not a customer
        $this->assertNull($order->customer);

        // Create order with actual customer
        $order2 = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'assigned_to' => User::factory()->create()->id,
        ]);
        $this->assertNotNull($order2->customer);
        $this->assertEquals($customer->id, $order2->customer->id);
    }

    /**
     * Test order date casting.
     */
    #[Test]
    public function it_checks_order_date_casting()
    {
        $estimatedDate = now()->addDays(7);
        $completedDate = now()->subDays(2);

        $order = Order::factory()->createQuietly([
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
    #[Test]
    public function it_checks_order_factory_states()
    {
        // Test completed state
        $completedOrder = Order::factory()->completed()->createQuietly();
        $this->assertEquals(OrderStatus::DELIVERED->value, $completedOrder->status->value);
        $this->assertNotNull($completedOrder->actual_completion);

        // Test open state
        $openOrder = Order::factory()->open()->createQuietly();
        $this->assertEquals(OrderStatus::OPEN->value, $openOrder->status->value);
        $this->assertNull($openOrder->actual_completion);
    }

    /**
     * Test has attachments trait methods.
     */
    #[Test]
    public function it_checks_has_attachments_trait_methods()
    {
        $order = Order::factory()->createQuietly();
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
    #[Test]
    public function it_checks_detaching_attachments()
    {
        $order = Order::factory()->createQuietly();
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
        $result = $order->detach('00');
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
    #[Test]
    public function it_checks_order_uses_uuid_as_primary_key()
    {
        $order = Order::factory()->createQuietly();

        // Check that ID is a valid UUID (Laravel 11 uses UUID v7)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $order->uuid
        );
    }

    /**
     * Test mass assignment protection.
     */
    #[Test]
    public function it_checks_mass_assignment_protection()
    {
        $fillableFields = [
            'customer_id',
            'title',
            'description',
            'status',
            'priority',
            // 'category_id',
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
    #[Test]
    public function it_checks_order_priority_values()
    {
        $priorities = [
            OrderPriority::LOW->value,
            OrderPriority::NORMAL->value,
            OrderPriority::HIGH->value,
            OrderPriority::URGENT->value
        ];

        foreach ($priorities as $priority) {
            $order = Order::factory()->createQuietly(['priority' => $priority]);
            $this->assertEquals($priority, $order->priority->value);
        }
    }

    /**
     * Test order status values.
     */
    #[Test]
    public function it_checks_order_status_values()
    {
        $statuses = [
            OrderStatus::OPEN->value,
            OrderStatus::IN_PROGRESS->value,
            OrderStatus::READY_FOR_DELIVERY->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::PAID->value,
            OrderStatus::RETURNED->value,
            OrderStatus::NOT_PAID->value,
            OrderStatus::CANCELLED->value,
            OrderStatus::ON_HOLD->value,
            OrderStatus::COMPLETED->value,
        ];

        foreach ($statuses as $status) {
            $order = Order::factory()->createQuietly(['status' => $status]);
            $this->assertEquals($status, $order->status->value);
        }
    }

    /**
     * Test order with null optional fields.
     */
    #[Test]
    public function it_checks_order_with_null_optional_fields()
    {
        $order = Order::factory()->createQuietly([
            'actual_completion' => null,
            'notes' => null,
            'assigned_to' => User::factory()->create()->id
        ]);

        $this->assertNull($order->actual_completion);
        $this->assertNull($order->notes);
        $this->assertNotNull($order->assigned_to);
        $this->assertNotNull($order->assignedTo);
    }
}
