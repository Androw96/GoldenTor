<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // The public website's document root (the folder that contains index.html).
        // Images uploaded in the admin are written to assets/uploads/... here, so the
        // static pages can use them as "assets/uploads/..." paths.
        'site' => [
            'driver' => 'local',
            'root' => env('GOLDENTOR_SITE_ROOT', dirname(base_path())),
            'url' => rtrim((string) env('GOLDENTOR_PUBLIC_URL', env('APP_URL', 'https://goldentor.hu')), '/'),
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'links' => [],

];
