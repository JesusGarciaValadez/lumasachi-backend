<?php

declare(strict_types=1);

return [
    'flash' => [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User archived successfully.',
    ],
    'validation' => [
        'forbidden_field' => 'This field cannot be submitted for administration.',
        'self_role' => 'An Administrator cannot change their own role.',
        'final_super_administrator' => 'The final active Super Administrator cannot be deactivated or demoted.',
        'final_super_administrator_delete' => 'The final active Super Administrator cannot be archived.',
    ],
    'errors' => [
        'unexpected' => 'We could not complete this user administration action. Please try again. Incident: :incident',
    ],
    'notifications' => [
        'subject' => 'User administration error',
        'intro' => 'An unexpected error occurred during user administration.',
        'incident' => 'Incident: :incident',
        'operation' => 'Operation: :operation',
    ],
    'pagination' => 'Pagination',
];
