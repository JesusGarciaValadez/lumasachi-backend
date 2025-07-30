<?php

namespace Modules\Lumasachi\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\UserType;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Carbon\Carbon;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Administrator
        $superAdmin = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@lumasachi.com',
            'role' => UserRole::SUPER_ADMINISTRATOR,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567890',
            'is_active' => true,
        ]);

        // Create Administrator
        $admin = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Administrator',
            'email' => 'admin@lumasachi.com',
            'role' => UserRole::ADMINISTRATOR,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567891',
            'is_active' => true,
        ]);

        // Create Employees
        $employee1 = User::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'email' => 'maria.garcia@lumasachi.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567892',
            'is_active' => true,
        ]);

        $employee2 = User::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Martinez',
            'email' => 'carlos.martinez@lumasachi.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567893',
            'is_active' => true,
        ]);

        $employee3 = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Rodriguez',
            'email' => 'ana.rodriguez@lumasachi.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567894',
            'is_active' => true,
        ]);

        // Create inactive employee
        $inactiveEmployee = User::factory()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Sanchez',
            'email' => 'pedro.sanchez@lumasachi.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567895',
            'is_active' => false,
            'notes' => 'Employee on extended leave',
        ]);

        // Create Individual Customers
        $customer1 = User::factory()->create([
            'first_name' => 'Laura',
            'last_name' => 'Thompson',
            'email' => 'laura.thompson@email.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567896',
            'address' => '123 Main St, New York, NY 10001',
            'is_active' => true,
        ]);

        $customer2 = User::factory()->create([
            'first_name' => 'David',
            'last_name' => 'Johnson',
            'email' => 'david.johnson@email.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567897',
            'address' => '456 Oak Ave, Los Angeles, CA 90001',
            'is_active' => true,
        ]);

        // Create Business Customers
        $businessCustomer1 = User::factory()->create([
            'first_name' => 'Robert',
            'last_name' => 'Williams',
            'email' => 'robert@techcorp.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::BUSINESS,
            'company' => 'Tech Corp Solutions',
            'phone_number' => '+1234567898',
            'address' => '789 Business Blvd, San Francisco, CA 94105',
            'is_active' => true,
            'notes' => 'VIP customer - priority service',
        ]);

        $businessCustomer2 = User::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Davis',
            'email' => 'sarah@designstudio.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::BUSINESS,
            'company' => 'Creative Design Studio',
            'phone_number' => '+1234567899',
            'address' => '321 Art District, Chicago, IL 60601',
            'is_active' => true,
        ]);

        // Create more random customers
        User::factory()->count(5)->create([
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);

        // Create Orders with different statuses

        // Order 1: Urgent order in progress
        $order1 = Order::factory()->create([
            'customer_id' => $businessCustomer1->id,
            'title' => 'Urgent Website Redesign',
            'description' => 'Complete redesign of company website with modern UI/UX. Must be responsive and include e-commerce functionality.',
            'status' => OrderStatus::IN_PROGRESS->value,
            'priority' => OrderPriority::URGENT->value,
            'category' => 'Web Development',
            'estimated_completion' => Carbon::now()->addDays(7),
            'created_by' => $businessCustomer1->id,
            'updated_by' => $employee1->id,
            'assigned_to' => $employee1->id,
            'notes' => 'Client needs this completed before product launch',
        ]);

        // Order 2: Normal priority order ready for delivery
        $order2 = Order::factory()->create([
            'customer_id' => $customer1->id,
            'title' => 'Business Card Design',
            'description' => 'Design and print 500 business cards with new company branding.',
            'status' => OrderStatus::READY_FOR_DELIVERY->value,
            'priority' => OrderPriority::NORMAL->value,
            'category' => 'Print Design',
            'estimated_completion' => Carbon::now()->addDays(3),
            'created_by' => $customer1->id,
            'updated_by' => $employee2->id,
            'assigned_to' => $employee2->id,
        ]);

        // Order 3: Completed and paid order
        $order3 = Order::factory()->create([
            'customer_id' => $customer2->id,
            'title' => 'Logo Design Project',
            'description' => 'Create new company logo with 3 variations and brand guidelines document.',
            'status' => OrderStatus::PAID->value,
            'priority' => OrderPriority::HIGH->value,
            'category' => 'Branding',
            'estimated_completion' => Carbon::now()->subDays(5),
            'actual_completion' => Carbon::now()->subDays(7),
            'created_by' => $customer2->id,
            'updated_by' => $employee3->id,
            'assigned_to' => $employee3->id,
            'notes' => 'Customer very satisfied with the result',
        ]);

        // Order 4: Open order not yet assigned
        $order4 = Order::factory()->create([
            'customer_id' => $businessCustomer2->id,
            'title' => 'Marketing Campaign Materials',
            'description' => 'Design materials for Q4 marketing campaign including posters, flyers, and social media graphics.',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'category' => 'Marketing',
            'estimated_completion' => Carbon::now()->addDays(14),
            'created_by' => $businessCustomer2->id,
            'updated_by' => $admin->id,
            'assigned_to' => null,
        ]);

        // Order 5: Cancelled order
        $order5 = Order::factory()->create([
            'customer_id' => $customer1->id,
            'title' => 'Product Photography',
            'description' => 'Professional photography session for new product line.',
            'status' => OrderStatus::CANCELLED->value,
            'priority' => OrderPriority::LOW->value,
            'category' => 'Photography',
            'estimated_completion' => Carbon::now()->addDays(10),
            'created_by' => $customer1->id,
            'updated_by' => $admin->id,
            'assigned_to' => null, // Cancelled orders shouldn't have assigned employees
            'notes' => 'Customer cancelled due to budget constraints',
        ]);

        // Create more random orders
        Order::factory()->count(10)->create();

        // Create Order History entries

        // History for Order 1
        OrderHistory::factory()->create([
            'order_id' => $order1->id,
            'status_from' => null,
            'status_to' => OrderStatus::OPEN->value,
            'priority_from' => null,
            'priority_to' => OrderPriority::URGENT->value,
            'description' => 'Order created with urgent priority',
            'created_by' => $businessCustomer1->id,
            'created_at' => $order1->created_at,
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order1->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'priority_from' => OrderPriority::URGENT->value,
            'priority_to' => OrderPriority::URGENT->value,
            'description' => 'Work started on the project',
            'notes' => 'Assigned to Maria Garcia for immediate attention',
            'created_by' => $admin->id,
            'created_at' => $order1->created_at->addHours(2),
        ]);

        // History for Order 2
        OrderHistory::factory()->create([
            'order_id' => $order2->id,
            'status_from' => OrderStatus::IN_PROGRESS->value,
            'status_to' => OrderStatus::READY_FOR_DELIVERY->value,
            'priority_from' => OrderPriority::NORMAL->value,
            'priority_to' => OrderPriority::NORMAL->value,
            'description' => 'Design completed and sent to print',
            'notes' => 'Cards will be ready for pickup tomorrow',
            'created_by' => $employee2->id,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        // History for Order 3 (complete lifecycle)
        OrderHistory::factory()->create([
            'order_id' => $order3->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::IN_PROGRESS->value,
            'description' => 'Designer started working on logo concepts',
            'created_by' => $employee3->id,
            'created_at' => $order3->created_at->addHours(1),
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order3->id,
            'status_from' => OrderStatus::IN_PROGRESS->value,
            'status_to' => OrderStatus::READY_FOR_DELIVERY->value,
            'description' => 'Logo designs completed and approved by customer',
            'created_by' => $employee3->id,
            'created_at' => $order3->actual_completion->subDays(2),
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order3->id,
            'status_from' => OrderStatus::READY_FOR_DELIVERY->value,
            'status_to' => OrderStatus::DELIVERED->value,
            'description' => 'Files delivered to customer via email',
            'created_by' => $employee3->id,
            'created_at' => $order3->actual_completion->subDays(1),
        ]);

        OrderHistory::factory()->create([
            'order_id' => $order3->id,
            'status_from' => OrderStatus::DELIVERED->value,
            'status_to' => OrderStatus::PAID->value,
            'description' => 'Payment received in full',
            'notes' => 'Payment via bank transfer',
            'created_by' => $admin->id,
            'created_at' => $order3->actual_completion,
        ]);

        // History for Order 5 (cancelled)
        OrderHistory::factory()->create([
            'order_id' => $order5->id,
            'status_from' => OrderStatus::OPEN->value,
            'status_to' => OrderStatus::CANCELLED->value,
            'description' => 'Order cancelled by customer',
            'notes' => 'Customer cited budget constraints',
            'created_by' => $admin->id,
        ]);

        // Create Attachments

        // Attachments for Order 1
        Attachment::factory()->pdf()->forOrder($order1)->create([
            'file_name' => 'website_requirements.pdf',
            'uploaded_by' => $businessCustomer1->id,
        ]);

        Attachment::factory()->image()->forOrder($order1)->create([
            'file_name' => 'design_mockup_v1.png',
            'uploaded_by' => $employee1->id,
        ]);

        // Attachments for Order 2
        Attachment::factory()->pdf()->forOrder($order2)->create([
            'file_name' => 'business_card_design_final.pdf',
            'uploaded_by' => $employee2->id,
        ]);

        // Attachments for Order 3
        Attachment::factory()->image()->forOrder($order3)->create([
            'file_name' => 'logo_final.png',
            'uploaded_by' => $employee3->id,
        ]);

        Attachment::factory()->pdf()->forOrder($order3)->create([
            'file_name' => 'brand_guidelines.pdf',
            'file_size' => 2097152, // 2MB
            'uploaded_by' => $employee3->id,
        ]);

        Attachment::factory()->spreadsheet()->forOrder($order3)->create([
            'file_name' => 'color_specifications.xlsx',
            'uploaded_by' => $employee3->id,
        ]);

        // Attachment for Order History (proof of payment)
        $paymentHistory = OrderHistory::where('order_id', $order3->id)
            ->where('status_to', OrderStatus::PAID->value)
            ->first();

        if ($paymentHistory) {
            Attachment::factory()->pdf()->forOrderHistory($paymentHistory)->create([
                'file_name' => 'payment_receipt.pdf',
                'uploaded_by' => $admin->id,
            ]);
        }

        // Create some random attachments for other orders
        $randomOrders = Order::inRandomOrder()->limit(5)->get();
        foreach ($randomOrders as $order) {
            Attachment::factory()
                ->count(rand(1, 3))
                ->forOrder($order)
                ->create();
        }

        $this->command->info('Database seeding completed successfully!');
        $this->command->info('Created:');
        $this->command->info('- 1 Super Administrator');
        $this->command->info('- 1 Administrator');
        $this->command->info('- 4 Employees (3 active, 1 inactive)');
        $this->command->info('- 9 Customers (mix of individual and business)');
        $this->command->info('- 15 Orders with various statuses');
        $this->command->info('- Multiple order history entries');
        $this->command->info('- Various attachments (PDFs, images, spreadsheets)');
    }
}
