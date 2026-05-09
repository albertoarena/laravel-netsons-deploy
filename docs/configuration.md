# Configuration Reference

The configuration file is published to `config/netsons-deploy.php`.

## Strategy

```php
'strategy' => env('NETSONS_DEPLOY_STRATEGY', 'ftp'),
```

Choose between `ftp` (FTP upload) or `git` (server-side git clone). See [FTP Strategy](ftp-strategy.md) and [Git Strategy](git-strategy.md).

## SSH

```php
'ssh' => [
    'host' => env('NETSONS_SSH_HOST'),
    'port' => env('NETSONS_SSH_PORT', 65100),
    'user' => env('NETSONS_SSH_USER'),
],
```

- **port** defaults to `65100` — the standard SSH port on Netsons shared hosting.
- Both strategies require SSH access for server-side operations (symlinks, migrations, caches).

## PHP Binary

```php
'php_binary' => env('NETSONS_PHP_BINARY', '/usr/local/bin/ea-php84'),
```

Netsons uses EasyApache PHP binaries. The `php` command may point to an older version, so always use the full path. Common values:

- `/usr/local/bin/ea-php82`
- `/usr/local/bin/ea-php83`
- `/usr/local/bin/ea-php84`

## Deploy Path

```php
'deploy_path' => env('NETSONS_DEPLOY_PATH', 'public_html'),
```

The remote directory relative to your home directory where the application is deployed.

## FTP Settings

```php
'ftp' => [
    'host' => env('NETSONS_FTP_HOST'),
    'port' => env('NETSONS_FTP_PORT', 21),
    'user' => env('NETSONS_FTP_USER'),
    'password' => env('NETSONS_FTP_PASS'),
    'protocol' => env('NETSONS_FTP_PROTOCOL', 'ftp'),
],
```

Only used with the FTP strategy. The FTP credentials are typically the same as your cPanel login.

## Git Settings

```php
'git' => [
    'repo' => env('NETSONS_GIT_REPO'),
    'branch' => env('NETSONS_GIT_BRANCH', 'main'),
],
```

Only used with the Git strategy. The repo URL should use SSH format (e.g., `git@github.com:user/repo.git`).

## Release Management

```php
'releases' => [
    'keep' => env('NETSONS_RELEASES_KEEP', 5),
],
```

Number of releases to retain. Older releases are automatically removed after each deployment.

## .htaccess

```php
'htaccess' => [
    'root' => true,
    'public' => true,
],
```

- **root** — generates a root `.htaccess` that rewrites all requests to the `public/` subdirectory
- **public** — ensures Laravel's rewrite rules are in `public/.htaccess`

## Environments

```php
'environments' => [
    'stage' => [
        'htaccess_root' => true,
    ],
    'production' => [
        'htaccess_root' => true,
    ],
],
```

Per-environment overrides. Currently supports toggling the root `.htaccess` generation.

## .env Mapping

```php
'env_mapping' => [
    // 'DB_PASSWORD' => 'PROD_DB_PASSWORD',
],
```

Maps `.env` keys to GitHub secret names. During deployment, values from GitHub secrets are injected into the shared `.env` file.

## Post-Deploy

```php
'post_deploy' => [
    'clear_cache' => true,
    'migrate' => true,
    'cache_config' => true,
    'cache_routes' => true,
    'cache_views' => true,
    'cache_events' => true,
    'queue_restart' => true,
],
```

Toggle individual post-deploy steps. All are enabled by default.

## Seeders

```php
'seeders' => [
    // 'RoleSeeder',
    // 'PermissionSeeder',
],
```

Seeder classes to run on the **first deploy only**. A `.first_deploy` flag file is created on initial setup and removed after seeders run.
