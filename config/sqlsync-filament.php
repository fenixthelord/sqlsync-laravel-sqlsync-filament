<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'SqlSync',

    'navigation_icon' => 'heroicon-o-arrow-path-rounded-square',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | Fluent API on the Plugin takes priority over these values.
    */
    'features' => [
        'dashboard' => true,
        'records' => true,
        'agents' => true,
        'logs' => true,
        'mappings' => true,
        'bridge' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    'online_threshold_minutes' => 5,

    'recent_logs_limit' => 20,

    'stats_cache_seconds' => 20,

];
