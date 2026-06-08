<?php

declare(strict_types=1);

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path'   => env('HORIZON_PATH', 'horizon'),
    'use'    => 'default',
    'prefix' => env('HORIZON_PREFIX', 'pha:horizon:'),

    'middleware' => ['web'],

    'waits' => [
        'redis:default'      => 60,
        'redis:eis-priority' => 30,
        'redis:reports'      => 300,
        'redis:payroll'      => 120,
    ],

    'trim' => [
        'recent'         => 60,
        'pending'        => 60,
        'completed'      => 60,
        'recent_failed'  => 10080,
        'failed'         => 10080,
        'monitored'      => 10080,
    ],

    'silenced' => [],
    'metrics'  => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,
    'memory_limit'     => 64,

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue'      => ['eis-priority', 'default', 'reports', 'payroll'],
            'balance'    => 'auto',
            'maxProcesses'         => 1,
            'maxTime'              => 0,
            'maxJobs'              => 0,
            'memory'               => 128,
            'tries'                => 3,
            'timeout'              => 120,
            'nice'                 => 0,
            'autoScalingStrategy'  => 'time',
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],
        'staging' => [
            'supervisor-default' => [
                'maxProcesses' => 4,
            ],
        ],
        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],
        ],
    ],
];
