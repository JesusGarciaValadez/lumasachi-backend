<?php

return [
    // Feature toggles managed by Laravel Pennant feature classes
    'motor-items' => [
        'disabled' => env('FEATURE_MOTOR_ITEMS_DISABLED', false),
        'rollout_date' => env('FEATURE_MOTOR_ITEMS_ROLLOUT_DATE', null), // e.g. '2025-10-31 00:00:00'
    ],
];
