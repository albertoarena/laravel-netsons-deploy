<?php

declare(strict_types=1);

return [
    // Deployment strategy: 'ftp' or 'git'
    'strategy' => env('NETSONS_DEPLOY_STRATEGY', 'ftp'),

    // Netsons server
    'ssh' => [
        'host' => env('NETSONS_SSH_HOST'),
        'port' => env('NETSONS_SSH_PORT', 65100),
        'user' => env('NETSONS_SSH_USER'),
    ],

    // Remote PHP binary path (ea-php version)
    'php_binary' => env('NETSONS_PHP_BINARY', '/usr/local/bin/ea-php84'),

    // Remote Composer binary path
    'composer_binary' => env('NETSONS_COMPOSER_BINARY', '/usr/local/bin/composer'),

    // Remote deploy path (relative to home directory)
    'deploy_path' => env('NETSONS_DEPLOY_PATH', 'public_html'),

    // FTP settings (only for FTP strategy)
    'ftp' => [
        'host' => env('NETSONS_FTP_HOST'),
        'port' => env('NETSONS_FTP_PORT', 21),
        'user' => env('NETSONS_FTP_USER'),
        'password' => env('NETSONS_FTP_PASS'),
        'protocol' => env('NETSONS_FTP_PROTOCOL', 'ftp'),
        'root_path' => env('NETSONS_FTP_ROOT_PATH', ''),
    ],

    // Git settings (only for git strategy)
    'git' => [
        'repo' => env('NETSONS_GIT_REPO'),
        'branch' => env('NETSONS_GIT_BRANCH', 'main'),
    ],

    // Release management
    'releases' => [
        'keep' => env('NETSONS_RELEASES_KEEP', 5),
    ],

    // .htaccess management
    'htaccess' => [
        'root' => true,
        'public' => true,
    ],

    // Environment-specific settings (stage/production)
    'environments' => [
        'stage' => [
            'htaccess_root' => true,
        ],
        'production' => [
            'htaccess_root' => true,
        ],
    ],

    // .env variables to inject from GitHub secrets
    // Format: 'ENV_KEY' => 'GITHUB_SECRET_NAME'
    'env_mapping' => [],

    // Post-deploy artisan commands
    'post_deploy' => [
        'clear_cache' => true,
        'migrate' => true,
        'cache_config' => true,
        'cache_routes' => true,
        'cache_views' => true,
        'cache_events' => true,
        'queue_restart' => true,
    ],

    // First-deploy seeders (run once on first deploy)
    'seeders' => [],
];
