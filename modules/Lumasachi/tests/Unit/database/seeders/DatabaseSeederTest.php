<?php

namespace Modules\Lumasachi\Tests\Unit\database\seeders;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\UserType;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\database\seeders\DatabaseSeeder;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations without seeding
        $this->artisan('migrate:fresh');
    }

    /**
     * Test that the seeder creates correct user hierarchy
     */
    public function test_seeder_creates_correct_user_hierarchy(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test super admin exists
        $superAdmin = User::where('email', 'superadmin@lumasachi.com')->first();
        $this->assertNotNull($superAdmin);
        $this->assertEquals(UserRole::SUPER_ADMINISTRATOR, $superAdmin->role);
        $this->assertTrue($superAdmin->is_active);

        // Test admin exists
        $admin = User::where('email', 'admin@lumasachi.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals(UserRole::ADMINISTRATOR, $admin->role);

        // Test employees (at least 4 should exist from seeder)
        $employees = User::where('role', UserRole::EMPLOYEE->value)->get();
        $this->assertGreaterThanOrEqual(4, $employees->count());

        // Check specific employees from seeder
        $seederEmployees = User::whereIn('email', [
            'maria.garcia@lumasachi.com',
            'carlos.martinez@lumasachi.com',
            'ana.rodriguez@lumasachi.com',
            'pedro.sanchez@lumasachi.com'
        ])->get();

        $this->assertEquals(4, $seederEmployees->count());
        $activeSeederEmployees = $seederEmployees->where('is_active', true);
        $this->assertEquals(3, $activeSeederEmployees->count());

        // Test specific employee
        $maria = User::where('email', 'maria.garcia@lumasachi.com')->first();
        $this->assertNotNull($maria);
        $this->assertEquals('Maria', $maria->first_name);
        $this->assertEquals('Garcia', $maria->last_name);
    }

    /**
     * Test that the seeder creates customers with correct types
     */
    public function test_seeder_creates_customers_with_correct_types(): void
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
        $this->assertEquals('Tech Corp Solutions', $techCorp->company);
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
    public function test_seeder_creates_orders_with_various_statuses(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test specific orders exist
        $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
        $this->assertNotNull($urgentOrder);
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $urgentOrder->status);
        $this->assertEquals(OrderPriority::URGENT->value, $urgentOrder->priority);
        $this->assertNotNull($urgentOrder->assigned_to);

        // Test order in ready for delivery status
        $readyOrder = Order::where('title', 'Business Card Design')->first();
        $this->assertNotNull($readyOrder);
        $this->assertEquals(OrderStatus::READY_FOR_DELIVERY->value, $readyOrder->status);

        // Test completed and paid order
        $paidOrder = Order::where('title', 'Logo Design Project')->first();
        $this->assertNotNull($paidOrder);
        $this->assertEquals(OrderStatus::PAID->value, $paidOrder->status);
        $this->assertNotNull($paidOrder->actual_completion);

        // Test open unassigned order
        $openOrder = Order::where('title', 'Marketing Campaign Materials')->first();
        $this->assertNotNull($openOrder);
        $this->assertEquals(OrderStatus::OPEN->value, $openOrder->status);
        $this->assertNull($openOrder->assigned_to);

        // Test cancelled order
        $cancelledOrder = Order::where('title', 'Product Photography')->first();
        $this->assertNotNull($cancelledOrder);
        $this->assertEquals(OrderStatus::CANCELLED->value, $cancelledOrder->status);
    }

    /**
     * Test that the seeder creates proper order history tracking
     */
    public function test_seeder_creates_proper_order_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test urgent order has creation history
        $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
        $creationHistory = OrderHistory::where('order_id', $urgentOrder->id)
            ->where('status_to', OrderStatus::OPEN->value)
            ->whereNull('status_from')
            ->first();
        $this->assertNotNull($creationHistory);

        // Test paid order has complete history
        $paidOrder = Order::where('title', 'Logo Design Project')->first();
        $paidOrderHistories = OrderHistory::where('order_id', $paidOrder->id)->get();
        $this->assertGreaterThanOrEqual(4, $paidOrderHistories->count());

        // Verify payment history exists
        $paymentHistory = $paidOrderHistories->where('status_to', OrderStatus::PAID->value)->first();
        $this->assertNotNull($paymentHistory);
        $this->assertStringContainsString('Payment received', $paymentHistory->description);
    }

    /**
     * Test that the seeder creates appropriate attachments
     */
    public function test_seeder_creates_appropriate_attachments(): void
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
        $this->assertGreaterThanOrEqual(3, $logoAttachments->count());

        // Test different file types
        $imageAttachment = $logoAttachments->firstWhere('file_name', 'logo_final.png');
        $this->assertNotNull($imageAttachment);
        $this->assertTrue($imageAttachment->isImage());

        $spreadsheetAttachment = $logoAttachments->firstWhere('file_name', 'color_specifications.xlsx');
        $this->assertNotNull($spreadsheetAttachment);
    }

    /**
     * Test that the seeder creates relationships correctly
     */
    public function test_seeder_creates_relationships_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test order relationships
        $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
        $this->assertNotNull($urgentOrder->customer);
        $this->assertNotNull($urgentOrder->assignedTo);
        $this->assertNotNull($urgentOrder->createdBy);
        $this->assertNotNull($urgentOrder->updatedBy);

        // Test that assigned employee is actually an employee
        $this->assertEquals(UserRole::EMPLOYEE, $urgentOrder->assignedTo->role);

        // Test order history relationships
        $history = OrderHistory::whereNotNull('notes')->first();
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
    public function test_seeder_maintains_business_logic_integrity(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Test that only customers can be order customers
        $orders = Order::with('customer')->whereNotNull('customer_id')->get();
        foreach ($orders as $order) {
            if ($order->customer) {
                $this->assertEquals(UserRole::CUSTOMER, $order->customer->role);
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
        $this->assertEquals(0, $cancelledOrders);
    }

    /**
     * Test database counts match expected values
     */
    public function test_database_counts_match_expected(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Users: 1 super admin + 1 admin + 4 employees + 9 customers (4 specific + 5 random) = 15
        $this->assertGreaterThanOrEqual(15, User::count());

        // Orders: 5 specific + 10 random = 15
        $this->assertEquals(15, Order::count());

        // Order histories: at least 8 specific entries
        $this->assertGreaterThanOrEqual(8, OrderHistory::count());

        // Attachments: at least 7 specific + some random
        $this->assertGreaterThanOrEqual(7, Attachment::count());
    }
}

