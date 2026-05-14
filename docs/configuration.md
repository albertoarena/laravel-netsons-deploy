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
    'retries' => env('NETSONS_SSH_RETRIES', 3),
    'retry_delay' => env('NETSONS_SSH_RETRY_DELAY', 10),
    'connect_timeout' => env('NETSONS_SSH_CONNECT_TIMEOUT', 30),
],
```

- **port** defaults to `65100` — the standard SSH port on Netsons shared hosting.
- **retries** — maximum number of SSH connection retry attempts (default `3`). Only connection failures (exit code 255) are retried; remote command errors are not.
- **retry_delay** — seconds to wait between retry attempts (default `10`).
- **connect_timeout** — SSH connection timeout in seconds (default `30`). Replaces the system default (~2 minutes) to fail fast and retry sooner.
- Both strategies require SSH access for server-side operations (symlinks, migrations, caches).

## PHP Binary

```php
'php_binary' => env('NETSONS_PHP_BINARY', '/usr/local/bin/ea-php84'),
```

Netsons uses EasyApache PHP binaries. The `php` command may point to an older version, so always use the full path. Common values:

- `/usr/local/bin/ea-php82`
- `/usr/local/bin/ea-php83`
- `/usr/local/bin/ea-php84`

## Composer Binary

```php
'composer_binary' => env('NETSONS_COMPOSER_BINARY', '/usr/local/bin/composer'),
```

The remote Composer binary path. The default `/usr/local/bin/composer` is the standard path on Netsons shared hosting. If your server has Composer at a different location (e.g., `/opt/cpanel/composer/bin/composer`), update this value.

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
    'root_path' => env('NETSONS_FTP_ROOT_PATH', ''),
],
```

Only used with the FTP strategy. The FTP credentials are typically the same as your cPanel login.

### FTP Root Path

The `root_path` controls how the FTP `server-dir` is computed. This depends on your FTP account's root directory:

- **Empty (default)** — FTP root is your home directory (`/home/user/`). The workflow uses `DEPLOY_PATH/releases/...` as server-dir.
- **Set to site directory** — FTP root is scoped to the site (`/home/user/mysite.com/`). The workflow uses `releases/...` as server-dir, since `DEPLOY_PATH` is already part of the FTP root.

Check your FTP root in cPanel > Files > FTP Accounts > Configure FTP Client.

## Git Settings

```php
'git' => [
    'repo' => env('NETSONS_GIT_REPO'),
    'branch' => env('NETSONS_GIT_BRANCH', 'main'),
],
```

Only used with the Git strategy. The repo URL must use HTTPS format (e.g., `https://github.com/user/repo.git`). SSH format (`git@github.com:...`) does not work — Netsons blocks outbound SSH on port 22.

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

> **Note:** The `env_mapping` in the PHP config is used for reference. The actual env variable management is handled via `netsons-deploy.json`. See [netsons-deploy.json](#netsons-deployjson) below.

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

Toggle individual post-deploy steps. All are enabled by default. The workflow always runs `package:discover --ansi` before cache commands.

## Seeders

```php
'seeders' => [
    // 'DatabaseSeeder',
],
```

Seeder classes to run on the **first deploy only**. A `.first_deploy` flag file is created on initial setup and removed after seeders run.

Seeders can also be configured interactively during `netsons:install` and stored in `netsons-deploy.json`. When both are set, `netsons-deploy.json` takes precedence.

During install, the command auto-detects seeders from your `composer.json`:

| Package | Suggested seeders |
|---------|-------------------|
| *(always)* | `DatabaseSeeder` |
| `spatie/laravel-permission` | `RoleSeeder`, `PermissionSeeder` |

Detected seeders are shown in a multiselect. You can also add custom seeders manually.

---

## netsons-deploy.json

The `netsons-deploy.json` file in your project root stores additional deployment configuration that the workflow generator uses. It is created by `netsons:install` and managed by `netsons:env`.

During `netsons:install`, the installer auto-detects variables from your `.env.example`:
- **Secret-backed** — `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `REDIS_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
- **Static** — any key with a non-placeholder value, excluding `APP_*`, `DB_*`, `MAIL_*`, `REDIS_*`, `AWS_*`, `VITE_*`, `LOG_*`, `CACHE_*` prefixes and placeholder values (empty, `null`, booleans, localhost, numeric ports)

Static values can be edited after selection (e.g., change `SESSION_DRIVER` from `file` to `database`).

When reconfiguring, the JSON is reset to defaults before collecting new values.

### Schema

```json
{
    "env_mapping": {
        "DB_DATABASE": "DB_DATABASE",
        "DB_USERNAME": "DB_USERNAME",
        "DB_PASSWORD": "DB_PASSWORD"
    },
    "env_static": {
        "SESSION_DRIVER": "database",
        "LARAVEL_PDF_DRIVER": "dompdf"
    },
    "build_env": {
        "VITE_APP_NAME": "My App"
    },
    "custom_commands": [
        "event-sourcing:cache-event-handlers 2>/dev/null || true"
    ],
    "seeders": [
        "DatabaseSeeder"
    ],
    "notifications": {
        "slack_webhook_secret": "SLACK_WEBHOOK_DEBUG"
    }
}
```

### env_mapping

Maps `.env` variable names to GitHub Secret names. During deployment, values are fetched from GitHub Secrets and injected into the remote `.env` file using sed with proper escaping for special characters.

```json
"env_mapping": {
    "DB_DATABASE": "DB_DATABASE",
    "DB_USERNAME": "DB_USERNAME",
    "DB_PASSWORD": "PROD_DB_PASS"
}
```

The key is the `.env` variable name, the value is the GitHub Secret name. They can differ (e.g., `DB_PASSWORD` mapped to `PROD_DB_PASS`).

### env_static

Static `.env` values that are fixed per deployment (not from secrets). These are written directly into the workflow.

```json
"env_static": {
    "SESSION_DRIVER": "database",
    "LARAVEL_PDF_DRIVER": "dompdf",
    "CACHE_STORE": "file"
}
```

### build_env

Environment variables available during the asset build step (`yarn build` / `npm run build`). Useful for Vite environment variables.

```json
"build_env": {
    "VITE_APP_NAME": "My Application",
    "VITE_API_URL": "https://api.example.com"
}
```

### custom_commands

Additional artisan commands to run during the post-deploy cache rebuild phase. These run after the standard cache commands and before `queue:restart`.

```json
"custom_commands": [
    "event-sourcing:cache-event-handlers 2>/dev/null || true",
    "permission:cache-reset",
    "horizon:terminate"
]
```

Common examples:

| Package | Command |
|---|---|
| Spatie Event Sourcing | `event-sourcing:cache-event-handlers 2>/dev/null \|\| true` |
| Spatie Permission | `permission:cache-reset` |
| Laravel Horizon | `horizon:terminate` |
| Laravel Telescope | `telescope:prune` |
| Laravel Scout | `scout:sync-index-settings` |

Use `2>/dev/null || true` for commands that may not be available in all environments.

### notifications

Optional Slack deploy notifications. When configured, the workflow sends a message on deploy success or failure.

```json
"notifications": {
    "slack_webhook_secret": "SLACK_WEBHOOK_DEBUG"
}
```

The value is the name of the GitHub Secret containing the Slack webhook URL. Add the `SLACK_WEBHOOK_DEBUG` (or your chosen name) secret to your GitHub repository.

### envaudit

Enables [envaudit](https://albertoarena.github.io/envaudit/) `.env` validation after deployment. When enabled, the workflow downloads the remote `.env` and validates it before proceeding with migrations.

```json
"envaudit": true
```

The validation step runs `npx @albertoarena/envaudit check --ci --no-color`. See the [envaudit CI integration guide](https://albertoarena.github.io/envaudit/getting-started/ci-integration/) for details.
