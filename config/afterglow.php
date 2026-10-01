<?php

return [

    'connection' => [
        'disk' => env('AFTERGLOW_FILESYSTEM_DISK', 'local'),
        'path' => env('AFTERGLOW_DATABASE_PATH', 'database.doltlite'),
        'lock' => [
            'store' => env('AFTERGLOW_LOCK_STORE'),
            'seconds' => (int) env('AFTERGLOW_LOCK_SECONDS', 0),
            'wait_seconds' => (int) env('AFTERGLOW_LOCK_WAIT_SECONDS', 10),
        ],
    ],

];
