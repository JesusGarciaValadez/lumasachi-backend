<?php

namespace Modules\Lumasachi\tests\Feature\app\Models;

use Tests\TestCase;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Lumasachi\app\Enums\UserRole;

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
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        
        // Create test order
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->employee->id,
            'status' => OrderStatus::OPEN,
            'priority' => OrderPriority::NORMAL
        ]);
    }

    /**
     * Test order history creation with valid data
     */
    public function test_can_create_order_history_with_valid_data(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::HIGH->value,
            'description' => 'Status changed to in progress',
            'notes' => 'Customer requested urgent processing',
            'created_by' => $this->employee->id
        ]);

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertDatabaseHas('order_histories', [
            'id' => $orderHistory->id,
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Status changed to in progress'
        ]);
    }

    /**
     * Test order history relationships
     */
    public function test_order_history_relationships(): void
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
     * Test enum casting for status fields
     */
    public function test_enum_casting_for_status_fields(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::DELIVERED->value,
            'created_by' => $this->employee->id
        ]);

        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_from);
        $this->assertInstanceOf(OrderStatus::class, $orderHistory->status_to);
        $this->assertEquals(OrderStatus::OPEN, $orderHistory->status_from);
        $this->assertEquals(OrderStatus::DELIVERED, $orderHistory->status_to);
    }

    /**
     * Test enum casting for priority fields
     */
    public function test_enum_casting_for_priority_fields(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'priority_from' => OrderPriority::LOW->value,
            'priority_to' => OrderPriority::URGENT->value,
            'created_by' => $this->employee->id
        ]);

        $this->assertInstanceOf(OrderPriority::class, $orderHistory->priority_from);
        $this->assertInstanceOf(OrderPriority::class, $orderHistory->priority_to);
        $this->assertEquals(OrderPriority::LOW, $orderHistory->priority_from);
        $this->assertEquals(OrderPriority::URGENT, $orderHistory->priority_to);
    }

    /**
     * Test nullable fields
     */
    public function test_nullable_fields(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Status changed',
            'created_by' => $this->employee->id
            // priority_from, priority_to, and notes are nullable
        ]);

        $this->assertNull($orderHistory->priority_from);
        $this->assertNull($orderHistory->priority_to);
        $this->assertNull($orderHistory->notes);
    }

    /**
     * Test order history with attachments using HasAttachments trait
     */
    public function test_order_history_with_attachments(): void
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
     * Test tracking status changes
     */
    public function test_tracking_status_changes(): void
    {
        // Create multiple history entries for status changes
        $statusChanges = [
            ['from' => OrderStatus::OPEN, 'to' => OrderStatus::IN_PROGRESS],
            ['from' => OrderStatus::IN_PROGRESS, 'to' => OrderStatus::DELIVERED],
        ];

        foreach ($statusChanges as $change) {
            OrderHistory::create([
                'order_id' => $this->order->id,
                'status_from' => $change['from']->value,
                'status_to' => $change['to']->value,
                'description' => "Status changed from {$change['from']->value} to {$change['to']->value}",
                'created_by' => $this->employee->id
            ]);
        }

        $histories = OrderHistory::where('order_id', $this->order->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $histories);
        $this->assertEquals(OrderStatus::OPEN, $histories[0]->status_from);
        $this->assertEquals(OrderStatus::IN_PROGRESS, $histories[0]->status_to);
        $this->assertEquals(OrderStatus::IN_PROGRESS, $histories[1]->status_from);
        $this->assertEquals(OrderStatus::DELIVERED, $histories[1]->status_to);
    }

    /**
     * Test tracking priority changes
     */
    public function test_tracking_priority_changes(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::URGENT->value,
            'description' => 'Priority escalated due to customer request',
            'notes' => 'Customer called and requested urgent processing',
            'created_by' => $this->employee->id
        ]);

        $this->assertEquals(OrderPriority::NORMAL, $orderHistory->priority_from);
        $this->assertEquals(OrderPriority::URGENT, $orderHistory->priority_to);
        $this->assertNotNull($orderHistory->notes);
    }

    /**
     * Test order history chronological ordering
     */
    public function test_order_history_chronological_ordering(): void
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
    public function test_order_history_with_different_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        // Create history entries by different users
        $history1 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id,
            'description' => 'Initial assignment'
        ]);

        $history2 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $admin->id,
            'description' => 'Admin review'
        ]);

        $history3 = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $employee2->id,
            'description' => 'Reassigned to another employee'
        ]);

        // Verify different users created entries
        $this->assertEquals($this->employee->id, $history1->createdBy->id);
        $this->assertEquals($admin->id, $history2->createdBy->id);
        $this->assertEquals($employee2->id, $history3->createdBy->id);
    }

    /**
     * Test filtering order history by status changes
     */
    public function test_filtering_order_history_by_status_changes(): void
    {
        // Create mixed history entries
        OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $this->employee->id
        ]);

        OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'status_from' => null,
            'status_to' => null,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::HIGH->value,
            'created_by' => $this->employee->id
        ]);

        OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::IN_PROGRESS->value,
            'status_to' => OrderStatus::DELIVERED->value,
            'created_by' => $this->employee->id
        ]);

        // Filter only status changes
        $statusChanges = OrderHistory::where('order_id', $this->order->id)
            ->whereNotNull('status_to')
            ->get();

        $this->assertCount(2, $statusChanges);
        foreach ($statusChanges as $change) {
            $this->assertNotNull($change->status_from);
            $this->assertNotNull($change->status_to);
        }
    }

    /**
     * Test UUID primary key functionality
     */
    public function test_uuid_primary_key(): void
    {
        $orderHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_by' => $this->employee->id
        ]);

        $this->assertIsString($orderHistory->id);
        $this->assertEquals(36, strlen($orderHistory->id)); // UUID length with hyphens
        // Laravel uses UUID v7 (ordered UUIDs) by default
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $orderHistory->id
        );
    }

    /**
     * Test mass assignment protection
     */
    public function test_mass_assignment_protection(): void
    {
        $data = [
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::HIGH->value,
            'description' => 'Test description',
            'notes' => 'Test notes',
            'created_by' => $this->employee->id,
            'created_at' => now()->subDays(10), // Should be ignored
            'updated_at' => now()->subDays(10), // Should be ignored
        ];

        $orderHistory = OrderHistory::create($data);

        // Verify fillable fields were set
        $this->assertEquals($data['order_id'], $orderHistory->order_id);
        $this->assertEquals($data['description'], $orderHistory->description);
        
        // Verify timestamps were not overridden
        $this->assertNotEquals($data['created_at'], $orderHistory->created_at);
        $this->assertNotEquals($data['updated_at'], $orderHistory->updated_at);
    }

    /**
     * Test OrderHistory factory
     */
    public function test_order_history_factory(): void
    {
        $orderHistory = OrderHistory::factory()->create();

        $this->assertInstanceOf(OrderHistory::class, $orderHistory);
        $this->assertNotNull($orderHistory->order_id);
        $this->assertNotNull($orderHistory->created_by);
        $this->assertNotNull($orderHistory->description);
        
        // Test factory with specific attributes
        $specificHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Custom description'
        ]);

        $this->assertEquals($this->order->id, $specificHistory->order_id);
        $this->assertEquals(OrderStatus::OPEN, $specificHistory->status_from);
        $this->assertEquals(OrderStatus::IN_PROGRESS, $specificHistory->status_to);
        $this->assertEquals('Custom description', $specificHistory->description);
    }

    /**
     * Test order history with only status change (no priority change)
     */
    public function test_order_history_with_only_status_change(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Status change only',
            'created_by' => $this->employee->id
        ]);

        $this->assertNotNull($orderHistory->status_from);
        $this->assertNotNull($orderHistory->status_to);
        $this->assertNull($orderHistory->priority_from);
        $this->assertNull($orderHistory->priority_to);
    }

    /**
     * Test order history with only priority change (no status change)
     */
    public function test_order_history_with_only_priority_change(): void
    {
        $orderHistory = OrderHistory::create([
            'order_id' => $this->order->id,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::HIGH->value,
            'description' => 'Priority change only',
            'created_by' => $this->employee->id
        ]);

        $this->assertNull($orderHistory->status_from);
        $this->assertNull($orderHistory->status_to);
        $this->assertNotNull($orderHistory->priority_from);
        $this->assertNotNull($orderHistory->priority_to);
    }
}
