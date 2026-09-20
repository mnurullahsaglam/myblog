<?php

declare(strict_types=1);

return [

    'default' => env('PENNANT_STORE', 'database'),

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'features',
        ],

    ],
];
