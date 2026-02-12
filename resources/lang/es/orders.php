<?php

declare(strict_types=1);

return [
    'order' => 'Orden',
    'orders' => 'Órdenes',
    'title' => 'Título',
    'status' => 'Estatus',
    'priority' => 'Prioridad',
    'customer' => 'Cliente',
    'assigned_to' => 'Asignado a',
    'estimated_completion' => 'Fecha estimada',
    'actual_completion' => 'Fecha real',
    'notes' => 'Notas',
    'no_notes' => 'Sin notas',
    'created_at' => 'Creada',
    'attachments' => 'Adjuntos',
    'history' => 'Historial',

    'status_labels' => [
        'Received' => 'Recibida',
        'Awaiting Review' => 'Esperando revisión',
        'Reviewed' => 'Revisada',
        'Awaiting Customer Approval' => 'Esperando aprobación del cliente',
        'Ready for Work' => 'Lista para trabajo',
        'Open' => 'Abierta',
        'In Progress' => 'En progreso',
        'Ready for Delivery' => 'Lista para entrega',
        'Completed' => 'Completada',
        'Delivered' => 'Entregada',
        'Paid' => 'Pagada',
        'Returned' => 'Devuelta',
        'Not Paid' => 'No pagada',
        'On Hold' => 'En espera',
        'Cancelled' => 'Cancelada',
    ],

    'validation' => [
        'mark_ready_for_delivery_status' => 'La orden debe estar en estado En progreso o Lista para trabajo.',
    ],

    'priority_labels' => [
        'Low' => 'Baja',
        'Normal' => 'Normal',
        'High' => 'Alta',
        'Urgent' => 'Urgente',
    ],
];
