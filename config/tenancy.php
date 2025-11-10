<?php

declare(strict_types=1);

return [
    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,
    'domain_model' => \App\Models\Domain::class,

    /**
     * Central domains - requests to these domains will NOT be treated as tenant requests
     */
    'tenant_database' => env('TENANT_DB_PREFIX', 'tenant_'),
    'central_domains' => [env('CENTRAL_DOMAIN', 'localhost')],

    // 'central_domains' => [
    //     '127.0.0.1',
    //     'localhost',
    //     env('APP_DOMAIN', 'lms.test'),
    //     env('CENTRAL_DOMAIN', 'central.lms.test'),
    // ],

    /**
     * Bootstrap tenancy features
     */
    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    /**
     * Database configuration
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => 'tenant',

        'prefix' => env('TENANT_DATABASE_PREFIX', 'tenant_'),
        'suffix' => env('TENANT_DATABASE_SUFFIX', ''),

        'managers' => [
            'mysql' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    /**
     * Cache configuration
     */
    'cache' => [
        'tag_base' => 'tenant',
    ],

    /**
     * Filesystem configuration
     */
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    /**
     * Redis configuration
     */
    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    /**
     * Features
     */
    'features' => [
        \Stancl\Tenancy\Features\UserImpersonation::class,
        \Stancl\Tenancy\Features\TenantConfig::class,
    ],

    /**
     * Migration parameters
     */
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /**
     * Seeder parameters
     */
    'seeder_parameters' => [
        '--class' => 'TenantDatabaseSeeder',
    ],
];
