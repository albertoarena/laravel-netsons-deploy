# INSTRUCTIONS.md — Step-by-Step Build Plan

This document is the authoritative build plan for Claude Code. Follow each phase in order.
After completing each phase, run the tests for that phase before proceeding.

## Phase 1: Project Skeleton

### 1.1 Create `composer.json`
```json
{
    "name": "albertoarena/laravel-netsons-deploy",
    "description": "Deploy Laravel applications to Netsons shared hosting via GitHub Actions (FTP or Git)",
    "type": "library",
    "license": "MIT",
    "keywords": ["laravel", "netsons", "deploy", "github-actions", "ftp", "ssh", "cpanel"],
    "authors": [
        {
            "name": "Alberto Arena",
            "homepage": "https://github.com/albertoarena"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/console": "^10.0|^11.0|^12.0|^13.0",
        "illuminate/support": "^10.0|^11.0|^12.0|^13.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0|^10.0|^11.0",
        "pestphp/pest": "^2.0|^3.0|^4.0",
        "pestphp/pest-plugin-laravel": "^2.0|^3.0|^4.0"
    },
    "autoload": {
        "psr-4": {
            "AlbertoArena\\NetsonsDeploy\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "AlbertoArena\\NetsonsDeploy\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "AlbertoArena\\NetsonsDeploy\\NetsonsDeployServiceProvider"
            ]
        }
    },
    "scripts": {
        "test": "pest",
        "test:coverage": "pest --coverage"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 1.2 Create `.gitignore`
```
/vendor
/node_modules
.env
.phpunit.cache
.phpunit.result.cache
/coverage
composer.lock
.DS_Store
Thumbbs.db
/.idea
/.vscode

# Private documentation (confidential)
/.docs
```

### 1.3 Create `LICENSE` (MIT, author: Alberto Arena)

### 1.4 Create `phpunit.xml` with Pest configuration

## Phase 2: Config & Service Provider

### 2.1 Create `config/netsons-deploy.php`
Full config as specified in CLAUDE.md. Every key must have sensible defaults.

### 2.2 Create `src/NetsonsDeployServiceProvider.php`
- Register config with `mergeConfigFrom()`
- Publish config with tag `netsons-deploy-config`
- Publish workflow stubs with tag `netsons-deploy-workflows`
- Publish htaccess stubs with tag `netsons-deploy-htaccess`
- Publish shell scripts with tag `netsons-deploy-scripts`
- Register commands: `InstallCommand`, `CheckCommand`
- Only register commands when running in console

## Phase 3: Service Classes

### 3.1 `src/Services/HtaccessGenerator.php`
- `generateRoot(): string` — generates root `.htaccess` that rewrites to `public/`
- `generatePublic(): string` — generates Laravel public `.htaccess` with auth header, XSRF header, trailing slash redirect, front controller rewrite
- Both methods return the content as a string
- Methods accept optional parameters for customization

### 3.2 `src/Services/ProxyIndexGenerator.php`
- `generate(string $releasePath): string` — generates proxy `index.php` content
- The proxy bootstraps Laravel from the given release path
- Must handle maintenance mode check
- Must be compatible with Laravel 10/11/12/13 bootstrap patterns

### 3.3 `src/Services/EnvManager.php`
- `generateSedCommands(array $mapping): string` — generates sed commands to inject env values
- `escapeForSed(string $value): string` — escape special chars for sed
- Mapping is `['ENV_KEY' => 'value']`
- Must handle values with special characters (& / \ quotes)

### 3.4 `src/Services/ReleaseManager.php`
- `generateTimestamp(): string` — returns `YYYYMMDDHHMMSS` UTC timestamp
- `getCleanupCommand(string $deployPath, int $keep): string` — returns shell command to remove old releases
- `getCreateCommand(string $deployPath, string $timestamp): string` — returns shell command to create release dir (copying previous if exists)
- `getSwitchCommand(string $deployPath, string $timestamp, string $phpBinary): string` — returns commands to activate release

## Phase 4: Strategy Classes

### 4.1 `src/Strategies/DeployStrategyInterface.php`
```php
interface DeployStrategyInterface
{
    public function name(): string;
    public function validate(array $config): array; // returns list of errors
    public function requiredSecrets(): array;
    public function requiredVariables(): array;
}
```

### 4.2 `src/Strategies/FtpStrategy.php`
- Implements `DeployStrategyInterface`
- `requiredSecrets()`: SSH_PRIVATE_KEY, SSH_KNOWN_HOSTS, FTP_HOST, FTP_USER, FTP_PASS, FTP_PORT
- `requiredVariables()`: DEPLOY_PATH, APP_ENV, APP_DEBUG, APP_URL

### 4.3 `src/Strategies/GitStrategy.php`
- Implements `DeployStrategyInterface`
- `requiredSecrets()`: SSH_PRIVATE_KEY, SSH_KNOWN_HOSTS
- `requiredVariables()`: DEPLOY_PATH, APP_ENV, APP_DEBUG, APP_URL, GIT_REPO, GIT_BRANCH

## Phase 5: Artisan Commands

### 5.1 `src/Commands/InstallCommand.php` (`netsons:install`)
Interactive command that:
1. Asks for deployment strategy (ftp/git)
2. Publishes config file
3. Publishes workflow templates to `.github/workflows/`
4. Publishes shell scripts (for git strategy)
5. Shows a checklist of required GitHub secrets to configure
6. Optionally generates `.htaccess` files

### 5.2 `src/Commands/CheckCommand.php` (`netsons:check`)
Validates readiness:
1. Checks config file exists and is valid
2. Lists required GitHub secrets for chosen strategy
3. Checks `.htaccess` stubs exist
4. Validates PHP version compatibility
5. Warns about common issues

## Phase 6: Stubs & Templates

### 6.1 Workflow Stub: `stubs/workflows/deploy.yml.stub`
A complete GitHub Actions workflow template. Must:
- Support `workflow_dispatch` with environment choice (stage/production)
- Accept strategy as a configurable variable at the top
- Include all steps: checkout, PHP setup, Node setup, Composer install, asset build, SSH setup, release creation, deploy (FTP or git), symlinks, .env management, htaccess, migrations, cache, proxy index.php, release switch, cleanup, notifications
- Use `%%PLACEHOLDER%%` syntax for values the user must customize
- Include clear comments explaining each section
- Support both stage and production environments
- Generate `.htaccess` for BOTH environments (not just production)
- The `.htaccess` root rewrite step must NOT be conditional on environment

### 6.2 `.htaccess` Stubs
- `stubs/htaccess/root.stub` — root rewrite to public/
- `stubs/htaccess/public.stub` — full Laravel public .htaccess

### 6.3 Shell Scripts in `stubs/scripts/`
- `setup-ssh.sh` — SSH key setup with agent and passphrase support
- `deploy-ftp.sh` — FTP strategy steps (delegates to FTP-Deploy-Action)
- `deploy-git.sh` — git clone/pull on server, then SCP built assets
- `post-deploy.sh` — shared post-deploy (symlinks, migrations, caching)
- `switch-release.sh` — copy static assets, upload proxy index.php, activate release
- `cleanup-releases.sh` — remove old releases beyond keep count

Each script must:
- Start with `#!/bin/bash` and `set -euo pipefail`
- Accept parameters via environment variables (documented at top)
- Log actions clearly with timestamps
- Handle errors with meaningful messages
- Be idempotent where possible

## Phase 7: GitHub Action Definition

### 7.1 `action.yml`
Composite action that:
1. Validates required inputs based on strategy
2. Sets up SSH
3. Creates release directory
4. Delegates to strategy-specific script
5. Runs post-deploy steps
6. Switches release
7. Cleans up old releases

## Phase 8: Tests

Write Pest tests for all service classes and commands.

### Unit Tests
- `tests/Unit/HtaccessGeneratorTest.php` — test both root and public generation, verify content
- `tests/Unit/ProxyIndexGeneratorTest.php` — test generation with various paths
- `tests/Unit/EnvManagerTest.php` — test sed command generation, special char escaping
- `tests/Unit/ReleaseManagerTest.php` — test timestamp format, cleanup commands, create commands
- `tests/Unit/ConfigTest.php` — test config defaults, validation

### Feature Tests
- `tests/Feature/InstallCommandTest.php` — test command execution, file publishing
- `tests/Feature/CheckCommandTest.php` — test validation output

### Shell Script Tests (optional, nice-to-have)
- Use bats-core or simple bash test scripts in `tests/Scripts/`
- Test script argument validation and error handling

## Phase 9: Documentation

### 9.1 `README.md`
Comprehensive README with:
- Badges (tests, packagist, license)
- Quick start (install, configure, deploy)
- Strategy comparison table
- Configuration reference
- GitHub secrets/variables reference
- Netsons-specific notes
- Troubleshooting link
- Contributing section

### 9.2 `docs/` folder (public)
- `configuration.md` — full config reference
- `ftp-strategy.md` — FTP setup and deployment flow
- `git-strategy.md` — Git setup and deployment flow
- `netsons-setup.md` — How to configure SSH keys, FTP, PHP version on Netsons cPanel
- `github-secrets.md` — Complete list of required secrets and variables
- `troubleshooting.md` — Common issues (SSH timeout, PHP version mismatch, permission errors, etc.)

### 9.3 `.docs/` folder (private, gitignored)
- `.docs/README.md` — explains this is confidential reference material
- `.docs/reference-deploy.yml` — the original deployment workflow from the source project (for reference only)
- `.docs/reference-notes.md` — notes about the source project's specific setup (seeders, env vars, Slack, etc.)

## Phase 10: Final Checks

1. Run full test suite: `composer test`
2. Run Pint for code style: `vendor/bin/pint`
3. Verify `.docs/` is in `.gitignore`
4. Verify no project-specific references in public files
5. Verify all stubs have correct placeholder syntax
6. Verify action.yml inputs match documentation
7. Review README for completeness
