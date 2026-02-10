<?php

namespace Tests\Feature\app\Models;

use Tests\TestCase;
use App\Models\OrderHistory;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;
    protected User $customer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

        // Create test order
        $this->order = Order::factory()->createQuietly([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value
        ]);
    }

    /**
     * Test order history creation with valid data
     */
    #[Test]
    public function it_checks_if_can_create_order_history_with_valid_data(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Status changed to in progress - Customer requested urgent processing',
            'created_by' => $this->employee->id
        ]);

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertDatabaseHas('order_histories', [
            'id' => $orderHistory->id,
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Status changed to in progress - Customer requested urgent processing'
        ]);
    }

    /**
     * Test order history relationships
     */
    #[Test]
    public function it_checks_if_order_history_relationships(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id
        ]);

        // Test order relationship
        $this->assertInstanceOf(Order::class, $orderHistory->order);
        $this->assertEquals($this->order->id, $orderHistory->order->id);

        // Test createdBy relationship
        $this->assertInstanceOf(User::class, $orderHistory->createdBy);
        $this->assertEquals($this->employee->id, $orderHistory->createdBy->id);
    }

    /**
     * Test tracking status changes
     */
    #[Test]
    public function it_checks_if_tracking_status_changes(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::DELIVERED->value,
            'created_by' => $this->employee->id
        ]);

        $this->assertEquals('status', $orderHistory->field_changed);
        $this->assertEquals(OrderStatus::OPEN->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::DELIVERED->value, $orderHistory->getRawOriginal('new_value'));
    }

    /**
     * Test tracking priority changes
     */
    #[Test]
    public function it_checks_if_tracking_priority_changes(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::LOW->value,
            'new_value' => OrderPriority::URGENT->value,
            'created_by' => $this->employee->id
        ]);

        $this->assertEquals('priority', $orderHistory->field_changed);
        $this->assertEquals(OrderPriority::LOW->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::URGENT->value, $orderHistory->getRawOriginal('new_value'));
    }

    /**
     * Test nullable fields
     */
    #[Test]
    public function it_checks_if_nullable_fields(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $this->employee->id
            // comment is nullable
        ]);

        $this->assertNull($orderHistory->comment);
    }

    /**
     * Test order history with attachments using HasAttachments trait
     */
    #[Test]
    public function it_checks_if_order_history_with_attachments(): void
    {
        Storage::fake('public');

        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id
        ]);

        // Test attaching a file
        $file = UploadedFile::fake()->create('change-log.pdf', 100, 'application/pdf');
        $attachment = $orderHistory->attach($file, $this->employee->id);

        $this->assertCount(1, $orderHistory->attachments);
        $this->assertEquals('change-log.pdf', $attachment->file_name);
        $this->assertEquals('order_history', $attachment->attachable_type);
        $this->assertTrue($orderHistory->hasAttachments());
    }

    /**
     * Test tracking multiple status changes
     */
    #[Test]
    public function it_checks_if_tracking_multiple_status_changes(): void
    {
        // Create multiple history entries for status changes
        $statusChanges = [
            ['from' => OrderStatus::OPEN->value, 'to' => OrderStatus::IN_PROGRESS->value],
            ['from' => OrderStatus::IN_PROGRESS->value, 'to' => OrderStatus::DELIVERED->value],
        ];

        foreach ($statusChanges as $change) {
            OrderHistory::create([
                'order_id' => $this->order->id,
                'field_changed' => 'status',
                'old_value' => $change['from'],
                'new_value' => $change['to'],
                'comment' => "Status changed from {$change['from']} to {$change['to']}",
                'created_by' => $this->employee->id
            ]);
        }

        $histories = OrderHistory::where('order_id', $this->order->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $histories);
        $this->assertEquals(OrderStatus::OPEN->value, $histories[0]->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $histories[0]->getRawOriginal('new_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $histories[1]->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::DELIVERED->value, $histories[1]->getRawOriginal('new_value'));
    }

    /**
     * Test tracking priority changes with comment
     */
    #[Test]
    public function it_checks_if_tracking_priority_changes_with_comment(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::URGENT->value,
            'comment' => 'Priority escalated due to customer request - Customer called and requested urgent processing',
            'created_by' => $this->employee->id
        ]);

        $this->assertEquals(OrderPriority::NORMAL->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::URGENT->value, $orderHistory->getRawOriginal('new_value'));
        $this->assertNotNull($orderHistory->comment);
    }

    /**
     * Test order history chronological ordering
     */
    #[Test]
    public function it_checks_if_order_history_chronological_ordering(): void
    {
        // Create history entries with different timestamps
        $history1 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id,
            'created_at' => now()->subDays(2)
        ]);

        $history2 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id,
            'created_at' => now()->subDay()
        ]);

        $history3 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id,
            'created_at' => now()
        ]);

        $histories = OrderHistory::where('order_id', $this->order->id)
            ->orderBy('created_at', 'desc')
            ->pluck('id')
            ->toArray();

        $this->assertEquals([$history3->id, $history2->id, $history1->id], $histories);
    }

    /**
     * Test order history with different users
     */
    #[Test]
    public function it_checks_if_order_history_with_different_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);

        // Create history entries by different users
        $history1 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id,
            'comment' => 'Initial assignment'
        ]);

        $history2 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $admin->id,
            'comment' => 'Admin review'
        ]);

        $history3 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $employee2->id,
            'comment' => 'Reassigned to another employee'
        ]);

        // Verify different users created entries
        $this->assertEquals($this->employee->id, $history1->createdBy->id);
        $this->assertEquals($admin->id, $history2->createdBy->id);
        $this->assertEquals($employee2->id, $history3->createdBy->id);
    }

    /**
     * Test filtering order history by field changes
     */
    #[Test]
    public function it_checks_if_filtering_order_history_by_field_changes(): void
    {
        // Create mixed history entries
        OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $this->employee->id
        ]);

        OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::HIGH->value,
            'created_by' => $this->employee->id
        ]);

        OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::IN_PROGRESS->value,
            'new_value' => OrderStatus::DELIVERED->value,
            'created_by' => $this->employee->id
        ]);

        // Filter only status changes
        $statusChanges = OrderHistory::where('order_id', $this->order->id)
            ->where('field_changed', 'status')
            ->get();

        $this->assertCount(2, $statusChanges);
        foreach ($statusChanges as $change) {
            $this->assertEquals('status', $change->field_changed);
        }
    }

    /**
     * Test UUID functionality
     */
    #[Test]
    public function it_checks_if_uuid_exists(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id
        ]);

        $this->assertIsString($orderHistory->uuid);
        $this->assertEquals(36, strlen($orderHistory->uuid)); // UUID length with hyphens
        // Laravel uses UUID v7 (ordered UUIDs) by default
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $orderHistory->uuid
        );
    }

    /**
     * Test mass assignment protection
     */
    #[Test]
    public function it_checks_if_mass_assignment_protection(): void
    {
        $data = [
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Test comment',
            'created_by' => $this->employee->id,
            'created_at' => now()->subDays(10), // Should be ignored
            'updated_at' => now()->subDays(10), // Should be ignored
        ];

        $orderHistory = OrderHistory::create($data);

        // Verify fillable fields were set
        $this->assertEquals($data['order_id'], $orderHistory->order_id);
        $this->assertEquals($data['comment'], $orderHistory->comment);

        // Verify timestamps were not overridden
        $this->assertNotEquals($data['created_at'], $orderHistory->created_at);
        $this->assertNotEquals($data['updated_at'], $orderHistory->updated_at);
    }

    /**
     * Test OrderHistory factory
     */
    #[Test]
    public function it_checks_if_order_history_factory(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertNotNull($orderHistory->order_id);
        $this->assertNotNull($orderHistory->created_by);
        $this->assertNotNull($orderHistory->field_changed);

        // Test factory with specific attributes
        $specificHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Custom comment'
        ]);

        $this->assertEquals($this->order->id, $specificHistory->order_id);
        $this->assertEquals('status', $specificHistory->field_changed);
        $this->assertEquals(OrderStatus::OPEN->value, $specificHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $specificHistory->getRawOriginal('new_value'));
        $this->assertEquals('Custom comment', $specificHistory->comment);
    }

    /**
     * Test order history with only status change
     */
    #[Test]
    public function it_checks_if_order_history_with_only_status_change(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Status change only',
            'created_by' => $this->employee->id
        ]);

        $this->assertEquals('status', $orderHistory->field_changed);
        $this->assertEquals(OrderStatus::OPEN->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $orderHistory->getRawOriginal('new_value'));
    }

    /**
     * Test order history with only priority change
     */
    #[Test]
    public function it_checks_if_order_history_with_only_priority_change(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'field_changed' => 'priority',
            'old_value' => OrderPriority::NORMAL->value,
            'new_value' => OrderPriority::HIGH->value,
            'comment' => 'Priority change only',
            'created_by' => $this->employee->id
        ]);

        $this->assertEquals('priority', $orderHistory->field_changed);
        $this->assertEquals(OrderPriority::NORMAL->value, $orderHistory->getRawOriginal('old_value'));
        $this->assertEquals(OrderPriority::HIGH->value, $orderHistory->getRawOriginal('new_value'));
    }
}
