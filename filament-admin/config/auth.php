<?php

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'admin_users',
    ],

    // The only login in this Laravel app is the Filament admin. Customers (table `users`)
    // keep logging in through the public website's own PHP API.
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'admin_users',
        ],
    ],

    'providers' => [
        'admin_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\AdminUser::class,
        ],
    ],

    'passwords' => [
        'admin_users' => [
            'provider' => 'admin_users',
            'table' => 'admin_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
