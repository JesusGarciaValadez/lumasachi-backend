<?php

declare(strict_types=1);

return [
    'greeting' => '¡Hola!',
    'greeting_admin' => 'Hola Administrador',
    'salutation' => 'Saludos',
    'view_order' => 'Ver Orden',

    'order_received' => [
        'subject' => 'Hemos recibido tu orden',
        'line' => 'Tu orden de trabajo ha sido recibida y está en nuestra cola.',
    ],

    'order_reviewed' => [
        'subject' => 'Tu orden ha sido revisada',
        'line' => 'Tu orden de trabajo ha sido revisada y está lista para tu aprobación.',
        'action' => 'Por favor inicia sesión para revisar y aprobar la cotización.',
    ],

    'order_ready_for_work' => [
        'subject' => 'Tu orden ha sido aprobada y está lista para trabajo',
        'line' => 'Tu orden de trabajo ha sido aprobada y el trabajo comenzará pronto.',
    ],

    'order_ready_for_delivery' => [
        'subject' => 'Tu orden está lista para entrega',
        'line' => 'Tu orden de trabajo está lista para entrega.',
    ],

    'order_delivered' => [
        'subject' => 'Tu orden ha sido entregada',
        'line' => 'Tu orden de trabajo ha sido entregada. ¡Gracias por tu preferencia!',
    ],

    'order_paid' => [
        'subject' => 'Pago recibido por tu orden',
        'line' => 'Hemos recibido el pago completo de tu orden de trabajo.',
    ],

    'audit' => [
        'subjects' => [
            'created' => 'Auditoría: Orden creada',
            'reviewed' => 'Auditoría: Orden revisada',
            'ready_for_work' => 'Auditoría: Orden lista para trabajo',
            'customer_approved' => 'Auditoría: Cliente aprobó servicios',
            'work_completed' => 'Auditoría: Trabajo completado en orden',
            'delivered' => 'Auditoría: Orden entregada',
            'received' => 'Auditoría: Orden recibida',
            'paid' => 'Auditoría: Orden pagada',
            'service_completed' => 'Auditoría: Servicio completado',
            'default' => 'Auditoría: Evento de orden',
        ],
        'line' => 'Ocurrió un evento auditable para una orden:',
        'event' => 'Evento: :event',
        'order' => 'Orden: :uuid',
        'status' => 'Estatus: :status',
    ],

    'order_label' => 'Orden: :uuid',
    'status_label' => 'Estatus: :status',
];
