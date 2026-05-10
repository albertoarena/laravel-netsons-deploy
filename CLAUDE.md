# CLAUDE.md — Instructions for Claude Code

## Project Overview

**Package:** `albertoarena/laravel-netsons-deploy`
**Type:** Laravel Composer package + reusable GitHub Action
**Purpose:** Deploy Laravel applications to Netsons shared hosting (cPanel, SSD plans) via GitHub Actions, supporting both FTP upload and SSH/git-clone strategies.
**License:** MIT

## Architecture

This package has two faces:

1. **Composer Package** — installed via `composer require albertoarena/laravel-netsons-deploy --dev`. Provides:
   - `php artisan netsons:install` — interactive setup, publishes config + generates `.github/workflows/deploy.yml`
   - `php artisan netsons:check` — shows local config, checks workflow file exists, lists required GitHub Secrets/Variables
   - Config file `config/netsons-deploy.php`
   - Publishable GitHub Actions workflow templates (`stubs/workflows/`)
   - Publishable `.htaccess` stubs for root and public directories

2. **Reusable GitHub Action** — usable as `albertoarena/laravel-netsons-deploy@v1` in any workflow. Accepts inputs for strategy (`ftp` or `git`), PHP version, Node version, etc. Internally delegates to shell scripts in `scripts/`.

## Netsons Hosting Constraints

These constraints apply to ALL Netsons shared hosting plans (cPanel-based):

- **SSH port is 65100** (not 22) — this must be configurable but default to 65100
- **PHP CLI path:** `/usr/local/bin/ea-phpXX` where XX is version (e.g., `ea-php84`). The `php` command may point to an older version. Always use the full ea-php path.
- **Composer path:** `/opt/cpanel/composer/bin/composer` or user-installed. Composer must be invoked with the correct PHP binary: `/usr/local/bin/ea-php84 /opt/cpanel/composer/bin/composer`
- **Document root:** `public_html/` by default. Laravel needs a proxy `index.php` or rewrite to `public/` subdirectory. Symlinks for document root are not reliable on shared hosting.
- **Git is available** on SSD 30+ plans
- **No root access**, no systemd, no supervisor
- **SSH key auth** is supported via cPanel > Security > SSH Access

## Deployment Strategies

### FTP Strategy (`strategy: ftp`)
- Uses `SamKirkland/FTP-Deploy-Action@v4.3.5` for incremental sync
- Builds assets in GitHub Actions runner (Composer + Node/Yarn)
- Uploads built artifacts to server
- Previous release is copied first so FTP transfers only diffs

### Git Strategy (`strategy: git`)
- Clones/pulls repo directly on server via SSH
- Runs `composer install --no-dev` on server using Netsons PHP CLI
- Assets must be built in GitHub Actions and uploaded via SCP (Node/Yarn not available on server)
- Uses sparse checkout to exclude unnecessary files

### Common to both strategies
- Release-based deployment with timestamped directories (`releases/YYYYMMDDHHMMSS/`)
- Shared directory for `.env` and `storage/` (symlinked into each release)
- Proxy `index.php` pattern (public/index.php points to active release)
- Keep N releases (default 5), prune older ones
- Post-deploy: clear caches, run migrations, rebuild caches
- `.htaccess` management for both root and public directories
- First-deploy detection (`.first_deploy` flag file)

## Directory Structure

```
├── CLAUDE.md                    # This file
├── INSTRUCTIONS.md              # Step-by-step build plan
├── README.md                    # Public documentation
├── LICENSE                      # MIT license
├── composer.json                # Package metadata
├── phpunit.xml                  # Test configuration
├── .gitignore
├── .docs/                       # PRIVATE — confidential reference material
│   ├── README.md                # Explains .docs purpose
│   └── reference-deploy.yml     # Reference deployment from source project
├── docs/                        # PUBLIC documentation
│   ├── configuration.md         # Config reference
│   ├── ftp-strategy.md          # FTP deployment guide
│   ├── git-strategy.md          # Git deployment guide
│   ├── netsons-setup.md         # Netsons cPanel SSH/FTP setup guide
│   ├── github-secrets.md        # Required GitHub secrets/variables
│   └── troubleshooting.md       # Common issues and fixes
├── config/
│   └── netsons-deploy.php       # Publishable config
├── src/
│   ├── NetsonsDeployServiceProvider.php
│   ├── Commands/
│   │   ├── InstallCommand.php   # netsons:install
│   │   └── CheckCommand.php     # netsons:check
│   ├── Strategies/
│   │   ├── DeployStrategyInterface.php
│   │   ├── FtpStrategy.php
│   │   └── GitStrategy.php
│   ├── Services/
│   │   ├── HtaccessGenerator.php
│   │   ├── ProxyIndexGenerator.php
│   │   ├── EnvManager.php
│   │   └── ReleaseManager.php
│   └── Stubs/                   # Embedded stubs (fallback)
├── stubs/
│   ├── workflows/
│   │   ├── deploy.yml.stub      # Main deploy workflow template
│   │   └── test.yml.stub        # Test workflow template (optional)
│   ├── htaccess/
│   │   ├── root.stub            # Root .htaccess (rewrite to public/)
│   │   └── public.stub          # Public .htaccess (Laravel rewrite rules)
│   └── scripts/
│       ├── deploy-ftp.sh        # FTP deploy orchestration
│       ├── deploy-git.sh        # Git deploy orchestration
│       ├── post-deploy.sh       # Shared post-deploy steps
│       ├── setup-ssh.sh         # SSH key setup
│       ├── switch-release.sh    # Release activation
│       └── cleanup-releases.sh  # Old release pruning
├── action.yml                   # GitHub Action definition
└── tests/
    ├── TestCase.php
    ├── Unit/
    │   ├── HtaccessGeneratorTest.php
    │   ├── ProxyIndexGeneratorTest.php
    │   ├── EnvManagerTest.php
    │   ├── ReleaseManagerTest.php
    │   └── ConfigTest.php
    └── Feature/
        ├── InstallCommandTest.php
        └── CheckCommandTest.php
```

## Coding Standards

- **PHP 8.2+** minimum (wide compatibility with Laravel 10/11/12/13)
- **Laravel 10/11/12/13** compatibility
- Follow PSR-12 coding style, enforced by **Laravel Pint**
- Use strict types: `declare(strict_types=1);` in every PHP file
- Tests with **Pest PHP**
- Type hints on all method parameters and return types
- No dependencies beyond Laravel framework and `laravel/prompts` (keep it lightweight)
- **Always use Laravel Prompts** (`use function Laravel\Prompts\...`) for all CLI interactions and output in commands. Never use `$this->info()`, `$this->warn()`, `$this->error()`, `$this->choice()`, `$this->ask()`, `$this->confirm()`, or `$this->table()` — use the equivalent Laravel Prompts functions: `info()`, `warning()`, `error()`, `select()`, `text()`, `confirm()`, `table()`, `note()`.
- Shell scripts must be POSIX-compatible (sh, not bash-specific) where possible, bash when needed
- **TDD is mandatory** — always write failing tests first, then implement. Never commit implementation code without corresponding tests. This applies to all changes: new features, bug fixes, refactors

## Before Committing

Always run these checks before committing:

1. **Tests:** `composer test` (all tests must pass)
2. **Code style:** `composer lint` (Laravel Pint must pass)
3. **Docs sync:** if the change affects commands, config, features, or user-facing behavior, update **all three doc locations**:
   - `README.md`
   - `docs/` (markdown files)
   - `website/src/content/docs/` (MDX pages)

If Pint reports issues, fix them with `composer lint:fix` and include the fixes in the commit.

## Config Design (`config/netsons-deploy.php`)

```php
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

    // Remote deploy path (relative to home directory)
    'deploy_path' => env('NETSONS_DEPLOY_PATH', 'public_html'),

    // FTP settings (only for FTP strategy)
    'ftp' => [
        'host' => env('NETSONS_FTP_HOST'),
        'port' => env('NETSONS_FTP_PORT', 21),
        'user' => env('NETSONS_FTP_USER'),
        'password' => env('NETSONS_FTP_PASS'),
        'protocol' => env('NETSONS_FTP_PROTOCOL', 'ftp'),
    ],

    // Git settings (only for git strategy)
    'git' => [
        'repo' => env('NETSONS_GIT_REPO'),       // e.g. git@github.com:user/repo.git
        'branch' => env('NETSONS_GIT_BRANCH', 'main'),
    ],

    // Release management
    'releases' => [
        'keep' => env('NETSONS_RELEASES_KEEP', 5),
    ],

    // .htaccess management
    'htaccess' => [
        'root' => true,      // Generate root .htaccess (rewrite to public/)
        'public' => true,    // Ensure Laravel rewrite rules in public/.htaccess
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
    'env_mapping' => [
        // Users will customize this
    ],

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
    'seeders' => [
        // 'RoleSeeder',
        // 'PermissionSeeder',
    ],
];
```

## GitHub Action Inputs (`action.yml`)

```yaml
inputs:
  strategy:
    description: 'Deployment strategy: ftp or git'
    required: true
  environment:
    description: 'Target environment: stage or production'
    required: true
  php-version:
    description: 'PHP version for build'
    default: '8.4'
  node-version:
    description: 'Node.js version for build'
    default: '22'
  package-manager:
    description: 'Node package manager: npm or yarn'
    default: 'yarn'
  remote-php:
    description: 'Remote PHP binary path on Netsons'
    default: '/usr/local/bin/ea-php84'
  deploy-path:
    description: 'Remote deploy path relative to home'
    required: true
  ssh-host:
    description: 'SSH hostname'
    required: true
  ssh-port:
    description: 'SSH port'
    default: '65100'
  ssh-user:
    description: 'SSH username'
    required: true
  ssh-private-key:
    description: 'SSH private key'
    required: true
  ssh-key-passphrase:
    description: 'SSH key passphrase (if any)'
    default: ''
  ssh-known-hosts:
    description: 'SSH known hosts entry'
    required: true
  releases-keep:
    description: 'Number of releases to keep'
    default: '5'
  # FTP-specific
  ftp-host:
    description: 'FTP hostname (FTP strategy only)'
  ftp-port:
    description: 'FTP port'
    default: '21'
  ftp-user:
    description: 'FTP username (FTP strategy only)'
  ftp-password:
    description: 'FTP password (FTP strategy only)'
  # Git-specific
  git-repo:
    description: 'Git repository URL (git strategy only)'
  git-branch:
    description: 'Git branch to deploy (git strategy only)'
    default: 'main'
```

## Build Order (for Claude Code)

Follow INSTRUCTIONS.md for the step-by-step build plan. Summary:

1. Initialize with composer.json, LICENSE, .gitignore
2. Build config and service provider
3. Build service classes (HtaccessGenerator, ProxyIndexGenerator, EnvManager, ReleaseManager)
4. Build Artisan commands
5. Build stubs (workflow templates, htaccess, scripts)
6. Build action.yml and shell scripts
7. Write tests
8. Write documentation (README, docs/)
9. Write .docs/ private reference

## Git Commit Conventions

## Format
- type: short subject line (max 50 chars)
- Detailed body paragraph explaining what and why (not how).

## Rules
- No Claude attribution - NEVER include "Generated with Claude Code" or "Co-Authored-By: Claude"
- Keep first line under 50 characters
- Use heredoc for multi-line commit messages

## Documentation Website

The documentation site lives in `website/` and is deployed to GitHub Pages at `https://albertoarena.github.io/laravel-netsons-deploy/`.

- **Tech stack:** Astro + Starlight
- **Deploy workflow:** `.github/workflows/deploy-docs.yml` (auto-deploys on push to main when `website/**` changes)
- **Local dev:** `cd website && npm run dev`

### Keeping docs in sync

When making changes to the package (new features, config changes, command updates, strategy changes), **always update**:

1. **`README.md`** — keep the public README in sync with any feature, config, or command changes
2. **`docs/`** — update the relevant markdown files in the docs folder
3. **`website/src/content/docs/`** — update the corresponding `.mdx` pages on the documentation website

This applies to all changes: new options, renamed parameters, updated defaults, new commands, removed features, etc. The README and docs site must always reflect the current state of the package.

## Important Rules

- **NEVER** include project-specific secrets, API keys, or credentials in any file
- **NEVER** reference the source project by name in public files (README, docs/, src/, tests/)
- Private reference material goes in `.docs/` which is gitignored
- The `.docs/` folder is confidential and must be in `.gitignore`
- All workflow stubs use placeholder variables (`${{ secrets.XXX }}`) — never real values
- Shell scripts must handle errors gracefully (set -e, trap for cleanup)
- Test with both FTP and git strategy paths
