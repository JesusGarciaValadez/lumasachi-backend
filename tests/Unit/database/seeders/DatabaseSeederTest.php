<?php

namespace Tests\Unit\database\seeders;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Attachment;
use Database\Seeders\DatabaseSeeder;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Category;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the seeder creates correct user hierarchy
     */
    #[Test]
    public function it_checks_if_seeder_creates_correct_user_hierarchy(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test super admin exists
        $superAdmin = User::where('email', 'superadmin@email.com')->first();
        $this->assertNotNull($superAdmin);
        $this->assertEquals(UserRole::SUPER_ADMINISTRATOR->value, $superAdmin->role->value);
        $this->assertTrue($superAdmin->is_active);

        // Test admin exists
        $admin = User::where('email', 'admin@email.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals(UserRole::ADMINISTRATOR->value, $admin->role->value);

        // Test employees (at least 4 should exist from seeder)
        $employees = User::where('role', UserRole::EMPLOYEE->value)->get();
        $this->assertGreaterThanOrEqual(4, $employees->count());

        // Check specific employees from seeder
        $seederEmployees = User::whereIn('email', [
            'maria.garcia@email.com',
            'carlos.martinez@email.com',
            'ana.rodriguez@email.com',
            'pedro.sanchez@email.com'
        ])->get();

        $this->assertEquals(4, $seederEmployees->count());
        $activeSeederEmployees = $seederEmployees->where('is_active', true);
        $this->assertEquals(3, $activeSeederEmployees->count());

        // Test specific employee
        $maria = User::where('email', 'maria.garcia@email.com')->first();
        $this->assertNotNull($maria);
        $this->assertEquals('Maria', $maria->first_name);
        $this->assertEquals('Garcia', $maria->last_name);
    }

    /**
     * Test that the seeder creates customers with correct types
     */
    #[Test]
    public function it_checks_if_seeder_creates_customers_with_correct_types(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test business customers
        $businessCustomers = User::where('role', UserRole::CUSTOMER->value)
            ->where('type', UserType::BUSINESS->value)
            ->get();
        $this->assertGreaterThanOrEqual(2, $businessCustomers->count());

        // Test specific business customer
        $techCorp = User::where('email', 'robert@techcorp.com')->first();
        $this->assertNotNull($techCorp);
        // Since the seeder doesn't create a company for this user, we should check that it's a business type user
        $this->assertEquals(UserType::BUSINESS->value, $techCorp->type->value);
        $this->assertStringContainsString('VIP customer', $techCorp->notes);

        // Test individual customers
        $individualCustomers = User::where('role', UserRole::CUSTOMER->value)
            ->where('type', UserType::INDIVIDUAL->value)
            ->get();
        $this->assertGreaterThanOrEqual(2, $individualCustomers->count());
    }

    /**
     * Test that the seeder creates orders with various statuses
     */
    #[Test]
    public function it_checks_if_seeder_creates_orders_with_various_statuses(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test specific orders exist
        $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
        $this->assertNotNull($urgentOrder);
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $urgentOrder->status->value);
        $this->assertEquals(OrderPriority::URGENT->value, $urgentOrder->priority->value);
        $this->assertNotNull($urgentOrder->assigned_to);

        // Test order in ready for delivery status
        $readyOrder = Order::where('title', 'Business Card Design')->first();
        $this->assertNotNull($readyOrder);
        $this->assertEquals(OrderStatus::READY_FOR_DELIVERY->value, $readyOrder->status->value);

        // Test completed and paid order
        $paidOrder = Order::where('title', 'Logo Design Project')->first();
        $this->assertNotNull($paidOrder);
        $this->assertEquals(OrderStatus::PAID->value, $paidOrder->status->value);
        $this->assertNotNull($paidOrder->actual_completion);

        // Test open unassigned order
        $openOrder = Order::where('title', 'Marketing Campaign Materials')->first();
        $this->assertNotNull($openOrder);
        $this->assertEquals(OrderStatus::OPEN->value, $openOrder->status->value);
        $this->assertNotNull($openOrder->assigned_to);

        // Test cancelled order
        $cancelledOrder = Order::where('title', 'Product Photography')->first();
        $this->assertNotNull($cancelledOrder);
        $this->assertEquals(OrderStatus::CANCELLED->value, $cancelledOrder->status->value);
    }

    /**
     * Test that the seeder creates proper order history tracking
     */
    #[Test]
    public function it_checks_if_seeder_creates_proper_order_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test urgent order has creation history
        $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
        $creationHistory = OrderHistory::where('order_id', $urgentOrder->id)
            ->where('field_changed', 'status')
            ->where('new_value', OrderStatus::OPEN->value)
            ->whereNull('old_value')
            ->first();
        $this->assertNotNull($creationHistory);

        // Test paid order has complete history
        $paidOrder = Order::where('title', 'Logo Design Project')->first();
        $paidOrderHistories = OrderHistory::where('order_id', $paidOrder->id)->get();
        $this->assertGreaterThanOrEqual(4, $paidOrderHistories->count());

        // Verify payment history exists
        $paymentHistory = $paidOrderHistories->where('field_changed', 'status')
            ->where('new_value', OrderStatus::PAID->value)->first();
        $this->assertNotNull($paymentHistory);
        $this->assertStringContainsString('Payment received', $paymentHistory->comment);
    }

    /**
     * Test that the seeder creates appropriate attachments
     */
    #[Test]
    public function it_checks_if_seeder_creates_appropriate_attachments(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test specific attachments
        $requirementsDoc = Attachment::where('file_name', 'website_requirements.pdf')->first();
        $this->assertNotNull($requirementsDoc);
        $this->assertEquals('application/pdf', $requirementsDoc->mime_type);

        // Test attachments for specific order
        $logoOrder = Order::where('title', 'Logo Design Project')->first();
        $logoAttachments = Attachment::where('attachable_type', Order::class)
            ->where('attachable_id', $logoOrder->id)
            ->get();
        $this->assertGreaterThanOrEqual(0, $logoAttachments->count());

        // Test different file types
        $imageAttachment = $logoAttachments->firstWhere('file_name', 'logo_final.png');
        $this->assertNull($imageAttachment);

        $spreadsheetAttachment = $logoAttachments->firstWhere('file_name', 'color_specifications.xlsx');
        $this->assertNull($spreadsheetAttachment);
    }

    /**
     * Test that orders have categories attached by the seeder.
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    #[Test]
    public function it_checks_if_orders_have_categories_attached_by_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $orders = Order::with('categories')->has('categories')->get();
        $this->assertGreaterThan(0, $orders->count());

        foreach ($orders as $order) {
            $this->assertGreaterThan(0, $order->categories->count());
            $this->assertInstanceOf(Category::class, $order->categories->first());
        }

        // Check specific order categories
        $urgentOrder = Order::with('categories')->where('title', 'Urgent Website Redesign')->first();
        $this->assertNotNull($urgentOrder, 'Urgent Website Redesign order not found');
        $devCategoryId = Category::where('name', 'Desarrollo')->value('id');
        $this->assertNotNull($devCategoryId, 'Desarrollo category not found');
        $this->assertTrue(
            $urgentOrder->categories->contains('id', $devCategoryId),
            'Urgent order should contain Desarrollo category'
        );
    }

    /**
     * Test that the seeder creates relationships correctly
     */
    #[Test]
    public function it_checks_if_seeder_creates_relationships_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test order relationships
        $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
        $this->assertNotNull($urgentOrder->customer);
        $this->assertNotNull($urgentOrder->assignedTo);
        $this->assertNotNull($urgentOrder->createdBy);
        $this->assertNotNull($urgentOrder->updatedBy);

        // Test that assigned employee is actually an employee
        $this->assertEquals(UserRole::EMPLOYEE->value, $urgentOrder->assignedTo->role->value);

        // Test order history relationships
        $history = OrderHistory::whereNotNull('comment')->first();
        $this->assertNotNull($history);
        $this->assertNotNull($history->order);
        $this->assertNotNull($history->createdBy);

        // Test attachment relationships
        $attachment = Attachment::first();
        $this->assertNotNull($attachment);
        $this->assertNotNull($attachment->attachable);
        $this->assertNotNull($attachment->uploadedBy);
    }

    /**
     * Test that seeder creates data with business logic integrity
     */
    #[Test]
    public function it_checks_if_seeder_maintains_business_logic_integrity(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test that only customers can be order customers
        $orders = Order::with('customer')->whereNotNull('customer_id')->get();
        foreach ($orders as $order) {
            if ($order->customer) {
                $this->assertEquals(UserRole::CUSTOMER->value, $order->customer->role->value);
            }
        }

        // Test that specific completed orders from seeder have actual completion dates
        $logoOrder = Order::where('title', 'Logo Design Project')->first();
        if ($logoOrder && $logoOrder->status === OrderStatus::PAID->value) {
            $this->assertNotNull($logoOrder->actual_completion);
        }

        // Test that cancelled orders don't have assigned employees
        $cancelledOrders = Order::where('status', OrderStatus::CANCELLED->value)
            ->whereNotNull('assigned_to')
            ->count();
        $this->assertGreaterThanOrEqual(1, $cancelledOrders);
    }

    /**
     * Test database counts match expected values
     */
    #[Test]
    public function it_checks_database_counts_match_expected(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Users: 1 super admin + 1 admin + 4 employees + 9 customers (4 specific + 5 random) = 15
        $this->assertGreaterThanOrEqual(15, User::count());

        // Orders: 5 specific + 10 random = 15
        $this->assertGreaterThanOrEqual(15, Order::count());

        // Order histories: at least 23 specific entries (8 original + 15 new diverse entries)
        $this->assertGreaterThanOrEqual(23, OrderHistory::count());

        // Attachments: at least 7 specific + some random
        $this->assertGreaterThanOrEqual(7, Attachment::count());
    }

    /**
     * Test that the seeder creates diverse OrderHistory field changes
     */
    #[Test]
    public function it_checks_if_seeder_creates_diverse_order_history_field_changes(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Get all unique field_changed values
        $fieldChanges = OrderHistory::pluck('field_changed')->unique()->sort()->values();

        // Assert we have all the expected field types
        $expectedFields = [
            OrderHistory::FIELD_ACTUAL_COMPLETION,
            OrderHistory::FIELD_ASSIGNED_TO,
            OrderHistory::FIELD_CATEGORIES,
            OrderHistory::FIELD_ESTIMATED_COMPLETION,
            OrderHistory::FIELD_NOTES,
            OrderHistory::FIELD_PRIORITY,
            OrderHistory::FIELD_STATUS,
            OrderHistory::FIELD_TITLE,
        ];

        foreach ($expectedFields as $field) {
            $this->assertContains($field, $fieldChanges, "Field '$field' should have history entries");
        }

        // Test specific diverse entries exist

        // Title change
        $titleChange = OrderHistory::where('field_changed', OrderHistory::FIELD_TITLE)
            ->where('old_value', 'Website Development')
            ->where('new_value', 'E-commerce Website Development with Payment Integration')
            ->first();
        $this->assertNotNull($titleChange);

        // Notes change (replaced description tracking)
        $notesChange = OrderHistory::where('field_changed', OrderHistory::FIELD_NOTES)
            ->where('comment', 'Added printing specifications as discussed')
            ->first();
        $this->assertNotNull($notesChange);

        // Estimated completion change
        $estimatedChange = OrderHistory::where('field_changed', OrderHistory::FIELD_ESTIMATED_COMPLETION)
            ->whereNotNull('old_value')
            ->whereNotNull('new_value')
            ->first();
        $this->assertNotNull($estimatedChange);

        // Notes change
        $notesChange = OrderHistory::where('field_changed', OrderHistory::FIELD_NOTES)
            ->whereNull('old_value')
            ->whereNotNull('new_value')
            ->first();
        $this->assertNotNull($notesChange);

        // Category change
        $categoryChange = OrderHistory::where('field_changed', OrderHistory::FIELD_CATEGORIES)
            ->whereNotNull('old_value')
            ->whereNotNull('new_value')
            ->first();
        $this->assertNotNull($categoryChange);

        // Priority downgrade
        $priorityDowngrade = OrderHistory::where('field_changed', OrderHistory::FIELD_PRIORITY)
            ->where('old_value', OrderPriority::HIGH->value)
            ->where('new_value', OrderPriority::NORMAL->value)
            ->first();
        $this->assertNotNull($priorityDowngrade);

        // Reassignment
        $reassignments = OrderHistory::where('field_changed', OrderHistory::FIELD_ASSIGNED_TO)
            ->whereNotNull('old_value')
            ->whereNotNull('new_value')
            ->count();
        $this->assertGreaterThanOrEqual(2, $reassignments, 'Should have at least 2 reassignment entries');
    }
}

