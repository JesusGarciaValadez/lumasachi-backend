<?php

declare(strict_types=1);

return [
    'greeting' => 'Hello!',
    'greeting_admin' => 'Hello Admin',
    'salutation' => 'Regards',
    'view_order' => 'View Order',

    'order_received' => [
        'subject' => 'We have received your order',
        'line' => 'Your work order has been received and is now in our queue.',
    ],

    'order_reviewed' => [
        'subject' => 'Your order has been reviewed',
        'line' => 'Your work order has been reviewed and is ready for your approval.',
        'action' => 'Please log in to review and approve the quotation.',
    ],

    'order_ready_for_work' => [
        'subject' => 'Your order has been approved and is ready for work',
        'line' => 'Your work order has been approved and work will begin shortly.',
    ],

    'order_ready_for_delivery' => [
        'subject' => 'Your order is ready for delivery',
        'line' => 'Your work order is ready for delivery.',
    ],

    'order_delivered' => [
        'subject' => 'Your order has been delivered',
        'line' => 'Your work order has been delivered. Thank you for your business!',
    ],

    'order_paid' => [
        'subject' => 'Payment received for your order',
        'line' => 'We have received full payment for your work order.',
    ],

    'audit' => [
        'subjects' => [
            'created' => 'Audit: Order created',
            'reviewed' => 'Audit: Order reviewed',
            'ready_for_work' => 'Audit: Order ready for work',
            'customer_approved' => 'Audit: Customer approved services',
            'work_completed' => 'Audit: Work completed on order',
            'delivered' => 'Audit: Order delivered',
            'received' => 'Audit: Order received',
            'paid' => 'Audit: Order paid',
            'service_completed' => 'Audit: Service completed',
            'default' => 'Audit: Order event',
        ],
        'line' => 'An auditable event occurred for an order:',
        'event' => 'Event: :event',
        'order' => 'Order: :uuid',
        'status' => 'Status: :status',
    ],

    'order_label' => 'Order: :uuid',
    'status_label' => 'Status: :status',
];
