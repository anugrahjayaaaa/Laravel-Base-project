<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pennant Store
    |--------------------------------------------------------------------------
    |
    | Here you will specify the default store that Pennant should use when
    | storing and resolving feature flag values. Pennant ships with the
    | ability to store flag values in an in-memory array or database.
    |
    | Supported: "array", "database"
    |
    */

    'default' => env('PENNANT_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Pennant Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure each of the stores that should be available to
    | Pennant. These stores shall be used to store resolved feature flag
    | values - you may configure as many as your application requires.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Application Feature Flags
    |--------------------------------------------------------------------------
    |
    | Declares every module-level feature flag with a human label. Values are
    | resolved/stored by Laravel Pennant (DB store). A flag absent here is
    | treated as disabled (fail-closed) by Pennant's `Feature::active()`.
    |
    */

    'features' => [
        // Access Management
        'users' => ['label' => 'Users', 'group' => 'access'],
        'roles' => ['label' => 'Roles', 'group' => 'access'],
        'permissions' => ['label' => 'Permissions', 'group' => 'access'],
        // Monitoring
        'audit' => ['label' => 'Audit Log', 'group' => 'monitoring'],
        'logs' => ['label' => 'Logs', 'group' => 'monitoring'],
        'telescope' => ['label' => 'Telescope', 'group' => 'monitoring'],
        'periscope' => ['label' => 'Periscope', 'group' => 'monitoring'],
        // Settings (system-level)
        'sessions' => ['label' => 'Sessions', 'group' => 'settings'],
        'api-tokens' => ['label' => 'API Tokens', 'group' => 'settings'],
        'translations' => ['label' => 'Translations', 'group' => 'settings'],
        'features' => ['label' => 'Features Management', 'group' => 'settings'], // toggle the Feature Management module itself
        // Billing (temporarily disabled — see docs/qa/remediation-tracker-v2.md)
        'plans' => ['label' => 'Plans', 'group' => 'billing', 'disabled' => true],
        'billing' => ['label' => 'Billing', 'group' => 'billing', 'disabled' => true],
    ],

];
