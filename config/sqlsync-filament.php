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
        'mappings' => false,
        'bridge' => true,
        // Off by default — this page permanently deletes SqlSync data
        // (and optionally your Products table). Enable deliberately:
        //   'reset' => true,
        // or via the Plugin: ->withReset(true)
        'reset' => false,
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
