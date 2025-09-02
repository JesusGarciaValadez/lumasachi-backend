<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\Order;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\OrderHistory;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CategorySeeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed companies first
        $this->call(CompanySeeder::class);

        // Seed categories
        $this->call(CategorySeeder::class);

        // Create Super Administrator
        $superAdmin = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@email.com',
            'role' => UserRole::SUPER_ADMINISTRATOR,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567890',
            'is_active' => true,
        ]);

        // Create Administrator
        $admin = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Administrator',
            'email' => 'admin@email.com',
            'role' => UserRole::ADMINISTRATOR,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567891',
            'is_active' => true,
        ]);

        // Create Employees
        $employee1 = User::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'email' => 'maria.garcia@email.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567892',
            'is_active' => true,
        ]);

        $employee2 = User::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Martinez',
            'email' => 'carlos.martinez@email.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567893',
            'is_active' => true,
        ]);

        $employee3 = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Rodriguez',
            'email' => 'ana.rodriguez@email.com',
            'role' => UserRole::EMPLOYEE,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567894',
            'is_active' => true,
        ]);

        // Create inactive employee
        $inactiveEmployee = User::factory()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Sanchez',
            'email' => 'pedro.sanchez@email.com',
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
            'is_active' => true,
        ]);

        $customer2 = User::factory()->create([
            'first_name' => 'David',
            'last_name' => 'Johnson',
            'email' => 'david.johnson@email.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::INDIVIDUAL,
            'phone_number' => '+1234567897',
            'is_active' => true,
        ]);

        // Create Business Customers
        $businessCustomer1 = User::factory()->create([
            'first_name' => 'Robert',
            'last_name' => 'Williams',
            'email' => 'robert@techcorp.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::BUSINESS,
            'phone_number' => '+1234567898',
            'is_active' => true,
            'notes' => 'VIP customer - priority service',
        ]);

        $businessCustomer2 = User::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Davis',
            'email' => 'sarah@designstudio.com',
            'role' => UserRole::CUSTOMER,
            'type' => UserType::BUSINESS,
            'phone_number' => '+1234567899',
            'is_active' => true,
        ]);

        // Create more random customers
        User::factory()->count(5)->create([
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);

        // Create Orders with different statuses

        // Order 1: Urgent order in progress
        $order1 = Order::factory()->createQuietly([
            'uuid' => Str::uuid()->toString(),
            'customer_id' => $businessCustomer1->id,
            'title' => 'Urgent Website Redesign',
            'description' => 'Complete redesign of company website with modern UI/UX. Must be responsive and include e-commerce functionality.',
            'status' => OrderStatus::IN_PROGRESS->value,
            'priority' => OrderPriority::URGENT->value,
            'category_id' => Category::where('name', 'Desarrollo')->first()->id,
            'estimated_completion' => Carbon::now()->addDays(7),
            'created_by' => $businessCustomer1->id,
            'updated_by' => $employee1->id,
            'assigned_to' => $employee1->id,
            'notes' => 'Client needs this completed before product launch',
        ]);

        // Order 2: Normal priority order ready for delivery
        $order2 = Order::factory()->createQuietly([
            'uuid' => Str::uuid()->toString(),
            'customer_id' => $customer1->id,
            'title' => 'Business Card Design',
            'description' => 'Design and print 500 business cards with new company branding.',
            'status' => OrderStatus::READY_FOR_DELIVERY->value,
            'priority' => OrderPriority::NORMAL->value,
            'category_id' => Category::where('name', 'Otros')->first()->id,
            'estimated_completion' => Carbon::now()->addDays(3),
            'created_by' => $customer1->id,
            'updated_by' => $employee2->id,
            'assigned_to' => $employee2->id,
        ]);

        // Order 3: Completed and paid order
        $order3 = Order::factory()->createQuietly([
            'uuid' => Str::uuid()->toString(),
            'customer_id' => $customer2->id,
            'title' => 'Logo Design Project',
            'description' => 'Create new company logo with 3 variations and brand guidelines document.',
            'status' => OrderStatus::PAID->value,
            'priority' => OrderPriority::HIGH->value,
            'category_id' => Category::where('name', 'Consultoría')->first()->id,
            'estimated_completion' => Carbon::now()->subDays(5),
            'actual_completion' => Carbon::now()->subDays(7),
            'created_by' => $customer2->id,
            'updated_by' => $employee3->id,
            'assigned_to' => $employee3->id,
            'notes' => 'Customer very satisfied with the result',
        ]);

        // Order 4: Open order not yet assigned
        $order4 = Order::factory()->createQuietly([
            'uuid' => Str::uuid()->toString(),
            'customer_id' => $businessCustomer2->id,
            'title' => 'Marketing Campaign Materials',
            'description' => 'Design materials for Q4 marketing campaign including posters, flyers, and social media graphics.',
            'status' => OrderStatus::OPEN->value,
            'priority' => OrderPriority::NORMAL->value,
            'category_id' => Category::where('name', 'Consultoría')->first()->id,
            'estimated_completion' => Carbon::now()->addDays(14),
            'created_by' => $businessCustomer2->id,
            'updated_by' => $admin->id,
            'assigned_to' => $admin->id,
        ]);

        // Order 5: Cancelled order
        $order5 = Order::factory()->createQuietly([
            'uuid' => Str::uuid()->toString(),
            'customer_id' => $customer1->id,
            'title' => 'Product Photography',
            'description' => 'Professional photography session for new product line.',
            'status' => OrderStatus::CANCELLED->value,
            'priority' => OrderPriority::LOW->value,
            'category_id' => Category::where('name', 'Otros')->first()->id,
            'estimated_completion' => Carbon::now()->addDays(10),
            'created_by' => $customer1->id,
            'updated_by' => $admin->id,
            'assigned_to' => $admin->id, // Cancelled orders shouldn't have assigned employees
            'notes' => 'Customer cancelled due to budget constraints',
        ]);

        // Create more random orders
        Order::factory()->count(10)->createQuietly([
            'uuid' => Str::uuid()->toString(),
            'assigned_to' => User::factory(),
        ]);

        // Create Order History entries

        // History for Order 1
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order1->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => null,
            'new_value' => OrderStatus::OPEN->value,
            'comment' => 'Order created with urgent priority',
            'created_by' => $businessCustomer1->id,
            'created_at' => $order1->created_at,
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order1->id,
            'field_changed' => OrderHistory::FIELD_PRIORITY,
            'old_value' => null,
            'new_value' => OrderPriority::URGENT->value,
            'comment' => 'Priority set to urgent',
            'created_by' => $businessCustomer1->id,
            'created_at' => $order1->created_at,
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order1->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Work started on the project',
            'created_by' => $admin->id,
            'created_at' => $order1->created_at->addHours(2),
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order1->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
            'old_value' => null,
            'new_value' => (string) $employee1->id,
            'comment' => 'Assigned to Maria Garcia for immediate attention',
            'created_by' => $admin->id,
            'created_at' => $order1->created_at->addHours(2),
        ]);

        // History for Order 2
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order2->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::IN_PROGRESS->value,
            'new_value' => OrderStatus::READY_FOR_DELIVERY->value,
            'comment' => 'Design completed and sent to print. Cards will be ready for pickup tomorrow',
            'created_by' => $employee2->id,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        // History for Order 3 (complete lifecycle)
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'comment' => 'Designer started working on logo concepts',
            'created_by' => $employee3->id,
            'created_at' => $order3->created_at->addHours(1),
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
            'old_value' => null,
            'new_value' => (string) $employee3->id,
            'comment' => 'Assigned to Ana Rodriguez',
            'created_by' => $admin->id,
            'created_at' => $order3->created_at->addHours(1),
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::IN_PROGRESS->value,
            'new_value' => OrderStatus::READY_FOR_DELIVERY->value,
            'comment' => 'Logo designs completed and approved by customer',
            'created_by' => $employee3->id,
            'created_at' => $order3->actual_completion->subDays(2),
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::READY_FOR_DELIVERY->value,
            'new_value' => OrderStatus::DELIVERED->value,
            'comment' => 'Files delivered to customer via email',
            'created_by' => $employee3->id,
            'created_at' => $order3->actual_completion->subDays(1),
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::DELIVERED->value,
            'new_value' => OrderStatus::PAID->value,
            'comment' => 'Payment received in full via bank transfer',
            'created_by' => $admin->id,
            'created_at' => $order3->actual_completion,
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_ACTUAL_COMPLETION,
            'old_value' => null,
            'new_value' => $order3->actual_completion->toISOString(),
            'comment' => 'Order completed',
            'created_by' => $employee3->id,
            'created_at' => $order3->actual_completion,
        ]);

        // History for Order 5 (cancelled)
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order5->id,
            'field_changed' => OrderHistory::FIELD_STATUS,
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::CANCELLED->value,
            'comment' => 'Order cancelled by customer. Customer cited budget constraints',
            'created_by' => $admin->id,
        ]);

        // Additional diverse history entries for various orders

        // Title change example
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order1->id,
            'field_changed' => OrderHistory::FIELD_TITLE,
            'old_value' => 'Website Development',
            'new_value' => 'E-commerce Website Development with Payment Integration',
            'comment' => 'Customer requested to update title to better reflect scope',
            'created_by' => $businessCustomer1->id,
            'created_at' => $order1->created_at->addHours(1),
        ]);

        // Notes change example (replaced description tracking)
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order2->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => 'Standard business card design',
            'new_value' => 'Professional business cards design for corporate use. Need 500 cards with gold foil accents.',
            'comment' => 'Added printing specifications as discussed',
            'created_by' => $customer1->id,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // Estimated completion change example
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order1->id,
            'field_changed' => OrderHistory::FIELD_ESTIMATED_COMPLETION,
            'old_value' => $order1->estimated_completion->toISOString(),
            'new_value' => $order1->estimated_completion->addDays(3)->toISOString(),
            'comment' => 'Delayed due to additional requirements for payment gateway integration',
            'created_by' => $employee1->id,
            'created_at' => $order1->created_at->addDays(2),
        ]);

        // Notes update example
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order2->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => null,
            'new_value' => 'Customer prefers minimalist design with company colors: Blue (#0066CC) and Grey (#666666)',
            'comment' => 'Design preferences discussed in meeting',
            'created_by' => $employee2->id,
            'created_at' => Carbon::now()->subDays(4),
        ]);

        // Category change example
        $developmentCategory = Category::where('name', 'Desarrollo')->first();
        $consultingCategory = Category::where('name', 'Consultoría')->first();

        if ($developmentCategory && $consultingCategory) {
            OrderHistory::factory()->create([
                'uuid' => Str::uuid()->toString(),
                'order_id' => $order4->id,
                'field_changed' => OrderHistory::FIELD_CATEGORY,
                'old_value' => (string) $developmentCategory->id,
                'new_value' => (string) $consultingCategory->id,
                'comment' => 'Reclassified from development to consulting based on project scope',
                'created_by' => $admin->id,
                'created_at' => $order4->created_at->addHours(3),
            ]);
        }

        // Priority change example (downgrade)
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order4->id,
            'field_changed' => OrderHistory::FIELD_PRIORITY,
            'old_value' => OrderPriority::HIGH->value,
            'new_value' => OrderPriority::NORMAL->value,
            'comment' => 'Customer agreed to extend deadline, reducing priority',
            'created_by' => $customer2->id,
            'created_at' => $order4->created_at->addDays(1),
        ]);

        // Assigned to change (reassignment)
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order2->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
            'old_value' => (string) $employee2->id,
            'new_value' => (string) $employee1->id,
            'comment' => 'Reassigned due to Carlos being on vacation next week',
            'created_by' => $admin->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // Another assigned to change (back to original)
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order2->id,
            'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
            'old_value' => (string) $employee1->id,
            'new_value' => (string) $employee2->id,
            'comment' => 'Carlos returned early, reassigning back to him',
            'created_by' => $admin->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        // Multiple field changes for order 4 to show complete tracking
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order4->id,
            'field_changed' => OrderHistory::FIELD_TITLE,
            'old_value' => 'Flyer Design',
            'new_value' => 'Promotional Flyer Design for Summer Sale',
            'comment' => 'Updated title for clarity',
            'created_by' => $customer2->id,
            'created_at' => $order4->created_at->addMinutes(30),
        ]);

        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order4->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => 'Standard flyer design',
            'new_value' => 'Promotional flyer design with QR code for website.',
            'comment' => 'Customer requested QR code addition',
            'created_by' => $customer2->id,
            'created_at' => $order4->created_at->addHours(4),
        ]);

        // Notes change with previous value
        OrderHistory::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order3->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => 'Initial concept approved',
            'new_value' => 'Initial concept approved. Final files delivered in AI, EPS, PNG, and SVG formats.',
            'comment' => 'Updated delivery notes',
            'created_by' => $employee3->id,
            'created_at' => $order3->actual_completion->subHours(1),
        ]);

        // Create Attachments

        // Attachments for Order 1
        Attachment::factory()->pdf()->for($order1, 'attachable')->create([
            'uuid' => Str::uuid()->toString(),
            'file_name' => 'website_requirements.pdf',
            'uploaded_by' => $businessCustomer1->id,
        ]);

        Attachment::factory()->image()->for($order1, 'attachable')->create([
            'uuid' => Str::uuid()->toString(),
            'file_name' => 'design_mockup_v1.png',
            'uploaded_by' => $employee1->id,
        ]);

        // Attachments for Order 2
        Attachment::factory()->pdf()->for($order2, 'attachable')->create([
            'uuid' => Str::uuid()->toString(),
            'file_name' => 'business_card_design_final.pdf',
            'uploaded_by' => $employee2->id,
        ]);

        // Attachments for Order 3
        Attachment::factory()->image()->for($order3, 'attachable')->create([
            'uuid' => Str::uuid()->toString(),
            'file_name' => 'logo_final.png',
            'uploaded_by' => $employee3->id,
        ]);

        Attachment::factory()->pdf()->for($order3, 'attachable')->create([
            'uuid' => Str::uuid()->toString(),
            'file_name' => 'brand_guidelines.pdf',
            'file_size' => 2097152, // 2MB
            'uploaded_by' => $employee3->id,
        ]);

        Attachment::factory()->spreadsheet()->for($order3, 'attachable')->create([
            'uuid' => Str::uuid()->toString(),
            'file_name' => 'color_specifications.xlsx',
            'uploaded_by' => $employee3->id,
        ]);

        // Attachment for Order History (proof of payment)
        $paymentHistory = OrderHistory::where('order_id', $order3->id)
            ->where('field_changed', OrderHistory::FIELD_STATUS)
            ->where('new_value', OrderStatus::PAID->value)
            ->first();

        if ($paymentHistory) {
            Attachment::factory()->pdf()->for($paymentHistory, 'attachable')->create([
                'uuid' => Str::uuid()->toString(),
                'file_name' => 'payment_receipt.pdf',
                'uploaded_by' => $admin->id,
            ]);
        }

        // Create some random attachments for other orders
        $randomOrders = Order::inRandomOrder()->limit(5)->get();
        foreach ($randomOrders as $order) {
            Attachment::factory()
                ->count(rand(1, 3))
                ->for($order, 'attachable')
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
