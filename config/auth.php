<?php

return [
    'defaults' => [
        'guard' => 'firebase',  // Usa el guard personalizado de Firebase
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'firebase',  // Cambiado para usar el guard personalizado de Firebase
            'provider' => 'users',
        ],
        'firebase' => [
            'driver' => 'firebase',  // Este será tu guard personalizado de Firebase
            'provider' => 'firebase_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent', 
            'model' => App\Models\User::class,
        ],
        'firebase_users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
