<?php

declare(strict_types=1);

return [
    'flash' => [
        'created' => 'Usuario creado correctamente.',
        'updated' => 'Usuario actualizado correctamente.',
        'deleted' => 'Usuario archivado correctamente.',
    ],
    'validation' => [
        'forbidden_field' => 'Este campo no se puede enviar para la administración.',
        'self_role' => 'Un Administrador no puede cambiar su propio rol.',
        'final_super_administrator' => 'El último Super Administrador activo no puede desactivarse ni degradarse.',
        'final_super_administrator_delete' => 'El último Super Administrador activo no puede archivarse.',
    ],
    'errors' => [
        'unexpected' => 'No pudimos completar esta acción de administración de usuarios. Inténtalo de nuevo. Incidente: :incident',
    ],
    'notifications' => [
        'subject' => 'Error de administración de usuarios',
        'intro' => 'Ocurrió un error inesperado durante la administración de usuarios.',
        'incident' => 'Incidente: :incident',
        'operation' => 'Operación: :operation',
    ],
    'pagination' => 'Paginación',
];
