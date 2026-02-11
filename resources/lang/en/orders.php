<?php

declare(strict_types=1);

return [
    'order' => 'Order',
    'orders' => 'Orders',
    'title' => 'Title',
    'status' => 'Status',
    'priority' => 'Priority',
    'customer' => 'Customer',
    'assigned_to' => 'Assigned to',
    'estimated_completion' => 'Estimated completion',
    'actual_completion' => 'Actual completion',
    'notes' => 'Notes',
    'no_notes' => 'No notes',
    'categories' => 'Categories',
    'created_at' => 'Created at',
    'attachments' => 'Attachments',
    'history' => 'History',

    'status_labels' => [
        'Received' => 'Received',
        'Awaiting Review' => 'Awaiting Review',
        'Reviewed' => 'Reviewed',
        'Awaiting Customer Approval' => 'Awaiting Customer Approval',
        'Ready for Work' => 'Ready for Work',
        'Open' => 'Open',
        'In Progress' => 'In Progress',
        'Ready for Delivery' => 'Ready for Delivery',
        'Completed' => 'Completed',
        'Delivered' => 'Delivered',
        'Paid' => 'Paid',
        'Returned' => 'Returned',
        'Not Paid' => 'Not Paid',
        'On Hold' => 'On Hold',
        'Cancelled' => 'Cancelled',
    ],

    'validation' => [
        'mark_ready_for_delivery_status' => 'Order must be in In Progress or Ready for Work status.',
    ],

    'priority_labels' => [
        'Low' => 'Low',
        'Normal' => 'Normal',
        'High' => 'High',
        'Urgent' => 'Urgent',
    ],
];
