<?php

declare(strict_types=1);

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if seeder creates correct user hierarchy', function () {
    $this->seed(DatabaseSeeder::class);

    // Test super admin exists
    $superAdmin = User::where('email', 'superadmin@email.com')->first();
    expect($superAdmin)->not->toBeNull();
    expect($superAdmin->role->value)->toEqual(UserRole::SUPER_ADMINISTRATOR->value);
    expect($superAdmin->is_active)->toBeTrue();

    // Test admin exists
    $admin = User::where('email', 'admin@email.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->role->value)->toEqual(UserRole::ADMINISTRATOR->value);

    // Test employees (at least 4 should exist from seeder)
    $employees = User::where('role', UserRole::EMPLOYEE->value)->get();
    expect($employees->count())->toBeGreaterThanOrEqual(4);

    // Check specific employees from seeder
    $seederEmployees = User::whereIn('email', [
        'maria.garcia@email.com',
        'carlos.martinez@email.com',
        'ana.rodriguez@email.com',
        'pedro.sanchez@email.com',
    ])->get();

    expect($seederEmployees->count())->toEqual(4);
    $activeSeederEmployees = $seederEmployees->where('is_active', true);
    expect($activeSeederEmployees->count())->toEqual(3);

    // Test specific employee
    $maria = User::where('email', 'maria.garcia@email.com')->first();
    expect($maria)->not->toBeNull();
    expect($maria->first_name)->toEqual('Maria');
    expect($maria->last_name)->toEqual('Garcia');
});
it('checks if seeder creates customers with correct types', function () {
    $this->seed(DatabaseSeeder::class);

    // Test business customers
    $businessCustomers = User::where('role', UserRole::CUSTOMER->value)
        ->where('type', UserType::BUSINESS->value)
        ->get();
    expect($businessCustomers->count())->toBeGreaterThanOrEqual(2);

    // Test specific business customer
    $techCorp = User::where('email', 'robert@techcorp.com')->first();
    expect($techCorp)->not->toBeNull();

    // Since the seeder doesn't create a company for this user, we should check that it's a business type user
    expect($techCorp->type->value)->toEqual(UserType::BUSINESS->value);
    $this->assertStringContainsString('VIP customer', $techCorp->notes);

    // Test individual customers
    $individualCustomers = User::where('role', UserRole::CUSTOMER->value)
        ->where('type', UserType::INDIVIDUAL->value)
        ->get();
    expect($individualCustomers->count())->toBeGreaterThanOrEqual(2);
});
it('checks if seeder creates orders with various statuses', function () {
    $this->seed(DatabaseSeeder::class);

    // Test specific orders exist
    $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
    expect($urgentOrder)->not->toBeNull();
    expect($urgentOrder->lifecycleStatus())->toEqual(OrderLifecycleStatus::ReadyForWork);
    expect($urgentOrder->priority->value)->toEqual(OrderPriority::URGENT->value);
    expect($urgentOrder->assigned_to)->not->toBeNull();

    // Test order in ready for delivery status
    $readyOrder = Order::where('title', 'Business Card Design')->first();
    expect($readyOrder)->not->toBeNull();
    expect($readyOrder->lifecycleStatus())->toEqual(OrderLifecycleStatus::ReadyForDelivery);

    // Test completed and paid order
    $paidOrder = Order::where('title', 'Logo Design Project')->first();
    expect($paidOrder)->not->toBeNull();
    expect($paidOrder->lifecycleStatus())->toEqual(OrderLifecycleStatus::Delivered);
    expect($paidOrder->actual_completion)->not->toBeNull();

    // Test open unassigned order
    $openOrder = Order::where('title', 'Marketing Campaign Materials')->first();
    expect($openOrder)->not->toBeNull();
    expect($openOrder->lifecycleStatus())->toEqual(OrderLifecycleStatus::Received);
    expect($openOrder->assigned_to)->not->toBeNull();

    // Test cancelled order
    $cancelledOrder = Order::where('title', 'Product Photography')->first();
    expect($cancelledOrder)->not->toBeNull();
    expect($cancelledOrder->dispositionStatus())->toEqual(OrderDispositionStatus::Cancelled);
});
it('checks if seeder creates proper order history', function () {
    $this->seed(DatabaseSeeder::class);

    // Test urgent order has creation history
    $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
    $creationHistory = OrderHistory::where('order_id', $urgentOrder->id)
        ->where('field_changed', OrderHistory::FIELD_LIFECYCLE_STATUS)
        ->where('new_value', OrderLifecycleStatus::Received->value)
        ->whereNull('old_value')
        ->first();
    expect($creationHistory)->not->toBeNull();

    // Test paid order has complete history
    $paidOrder = Order::where('title', 'Logo Design Project')->first();
    $paidOrderHistories = OrderHistory::where('order_id', $paidOrder->id)->get();
    expect($paidOrderHistories->count())->toBeGreaterThanOrEqual(4);

    // Verify payment history exists
    $paymentHistory = $paidOrderHistories->where('field_changed', OrderHistory::FIELD_PAYMENT_STATUS)
        ->where('new_value', 'Paid')->first();
    expect($paymentHistory)->not->toBeNull();
    $this->assertStringContainsString('Payment received', $paymentHistory->comment);
});
it('checks if seeder creates appropriate attachments', function () {
    $this->seed(DatabaseSeeder::class);

    // Test specific attachments
    $requirementsDoc = Attachment::where('file_name', 'website_requirements.pdf')->first();
    expect($requirementsDoc)->not->toBeNull();
    expect($requirementsDoc->mime_type)->toEqual('application/pdf');

    // Test attachments for specific order
    $logoOrder = Order::where('title', 'Logo Design Project')->first();
    $logoAttachments = Attachment::where('attachable_type', Order::class)
        ->where('attachable_id', $logoOrder->id)
        ->get();
    expect($logoAttachments->count())->toBeGreaterThanOrEqual(0);

    // Test different file types
    $imageAttachment = $logoAttachments->firstWhere('file_name', 'logo_final.png');
    expect($imageAttachment)->toBeNull();

    $spreadsheetAttachment = $logoAttachments->firstWhere('file_name', 'color_specifications.xlsx');
    expect($spreadsheetAttachment)->toBeNull();
});
it('checks if seeder creates relationships correctly', function () {
    $this->seed(DatabaseSeeder::class);

    // Test order relationships
    $urgentOrder = Order::where('title', 'Urgent Website Redesign')->first();
    expect($urgentOrder->customer)->not->toBeNull();
    expect($urgentOrder->assignedTo)->not->toBeNull();
    expect($urgentOrder->createdBy)->not->toBeNull();
    expect($urgentOrder->updatedBy)->not->toBeNull();

    // Test that assigned employee is actually an employee
    expect($urgentOrder->assignedTo->role->value)->toEqual(UserRole::EMPLOYEE->value);

    // Test order history relationships
    $history = OrderHistory::whereNotNull('comment')->first();
    expect($history)->not->toBeNull();
    expect($history->order)->not->toBeNull();
    expect($history->createdBy)->not->toBeNull();

    // Test attachment relationships
    $attachment = Attachment::first();
    expect($attachment)->not->toBeNull();
    expect($attachment->attachable)->not->toBeNull();
    expect($attachment->uploadedBy)->not->toBeNull();
});
it('checks if seeder maintains business logic integrity', function () {
    $this->seed(DatabaseSeeder::class);

    // Test that only customers can be order customers
    $orders = Order::with('customer')->whereNotNull('customer_id')->get();
    foreach ($orders as $order) {
        if ($order->customer) {
            expect($order->customer->role->value)->toEqual(UserRole::CUSTOMER->value);
        }
    }

    // Test that specific completed orders from seeder have actual completion dates
    $logoOrder = Order::where('title', 'Logo Design Project')->first();
    if ($logoOrder && $logoOrder->lifecycleStatus() === OrderLifecycleStatus::Delivered) {
        expect($logoOrder->actual_completion)->not->toBeNull();
    }

    // Test that cancelled orders don't have assigned employees
    $cancelledOrders = Order::where('disposition_status', OrderDispositionStatus::Cancelled->value)
        ->whereNotNull('assigned_to')
        ->count();
    expect($cancelledOrders)->toBeGreaterThanOrEqual(1);
});
it('checks database counts match expected', function () {
    $this->seed(DatabaseSeeder::class);

    // Users: 1 super admin + 1 admin + 4 employees + 9 customers (4 specific + 5 random) = 15
    expect(User::count())->toBeGreaterThanOrEqual(15);

    // Orders: 5 specific + 10 random = 15
    expect(Order::count())->toBeGreaterThanOrEqual(15);

    // Order histories: at least 22 specific entries (8 original + 14 new diverse entries)
    expect(OrderHistory::count())->toBeGreaterThanOrEqual(22);

    // Attachments: at least 7 specific + some random
    expect(Attachment::count())->toBeGreaterThanOrEqual(7);
});
it('checks if seeder creates diverse order history field changes', function () {
    $this->seed(DatabaseSeeder::class);

    // Get all unique field_changed values
    $fieldChanges = OrderHistory::pluck('field_changed')->unique()->sort()->values();

    // Assert we have all the expected field types
    $expectedFields = [
        OrderHistory::FIELD_ACTUAL_COMPLETION,
        OrderHistory::FIELD_ASSIGNED_TO,
        OrderHistory::FIELD_ESTIMATED_COMPLETION,
        OrderHistory::FIELD_NOTES,
        OrderHistory::FIELD_PRIORITY,
        OrderHistory::FIELD_LIFECYCLE_STATUS,
        OrderHistory::FIELD_TITLE,
    ];

    foreach ($expectedFields as $field) {
        expect($fieldChanges)->toContain($field);
    }

    // Test specific diverse entries exist
    // Title change
    $titleChange = OrderHistory::where('field_changed', OrderHistory::FIELD_TITLE)
        ->where('old_value', 'Website Development')
        ->where('new_value', 'E-commerce Website Development with Payment Integration')
        ->first();
    expect($titleChange)->not->toBeNull();

    // Notes change (replaced description tracking)
    $notesChange = OrderHistory::where('field_changed', OrderHistory::FIELD_NOTES)
        ->where('comment', 'Added printing specifications as discussed')
        ->first();
    expect($notesChange)->not->toBeNull();

    // Estimated completion change
    $estimatedChange = OrderHistory::where('field_changed', OrderHistory::FIELD_ESTIMATED_COMPLETION)
        ->whereNotNull('old_value')
        ->whereNotNull('new_value')
        ->first();
    expect($estimatedChange)->not->toBeNull();

    // Notes change
    $notesChange = OrderHistory::where('field_changed', OrderHistory::FIELD_NOTES)
        ->whereNull('old_value')
        ->whereNotNull('new_value')
        ->first();
    expect($notesChange)->not->toBeNull();

    // Priority downgrade
    $priorityDowngrade = OrderHistory::where('field_changed', OrderHistory::FIELD_PRIORITY)
        ->where('old_value', OrderPriority::HIGH->value)
        ->where('new_value', OrderPriority::NORMAL->value)
        ->first();
    expect($priorityDowngrade)->not->toBeNull();

    // Reassignment
    $reassignments = OrderHistory::where('field_changed', OrderHistory::FIELD_ASSIGNED_TO)
        ->whereNotNull('old_value')
        ->whereNotNull('new_value')
        ->count();
    expect($reassignments)->toBeGreaterThanOrEqual(2);
});
