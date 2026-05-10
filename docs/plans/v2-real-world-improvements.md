# Plan: v2 Real-World Improvements

**Origin:** Real-world testing of laravel-netsons-deploy in a production project revealed gaps between the generated workflow and production deployment needs.

**Date:** 2026-05-10

---

## Summary of Changes

Nine improvement areas identified, organized into 8 work items:

| # | Item | Type | Priority | Status |
|---|------|------|----------|--------|
| W1 | Dependency caching (Composer + Node) | Workflow stub | Must-fix | DONE |
| W2 | env_mapping wired into workflow + env_static + sed escaping | Config + Workflow + JSON | Must-fix | DONE |
| W3 | key:generate on first deploy | Workflow stub | Must-fix | DONE |
| W4 | First-deploy seeders + .first_deploy cleanup | Workflow stub | Must-fix | DONE |
| W5 | SSH cleanup step | Workflow stub | Must-fix | DONE |
| W6 | Custom post-deploy artisan commands | Config + Workflow | Enhancement | DONE |
| W7 | Build env vars | Config + Workflow | Enhancement | DONE |
| W8 | Slack notifications (opt-in) | Config + Workflow | Enhancement | DONE |
| W9 | FTP root path config | Config + Workflow | Enhancement | DONE |
| W10 | `netsons:env` command + install-time env setup | New command | Enhancement | DONE |
| W11 | `netsons-deploy.json` as env source of truth | New file | Enhancement | DONE |

---

## Architecture Decision: netsons-deploy.json

Env variable configuration will be stored in `netsons-deploy.json` (project root), not in the PHP config. This file is:
- Easy to read/write programmatically (JSON)
- Created by `netsons:install` (interactive questions)
- Updated by `netsons:env` command
- Read by `netsons:install` when regenerating deploy.yml

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
    "package:discover --ansi",
    "event-sourcing:cache-event-handlers 2>/dev/null || true"
  ],
  "notifications": {
    "slack_webhook_secret": "SLACK_WEBHOOK_DEBUG"
  }
}
```

The PHP config file (`config/netsons-deploy.php`) keeps infrastructure settings (strategy, SSH, FTP, releases, htaccess, post_deploy toggles). The JSON file handles user-customizable deployment variables and commands.

---

## W1: Dependency Caching (Composer + Node)

### What
Add Composer and Node dependency caching steps to `deploy.yml.stub`.

### Changes

**`stubs/workflows/deploy.yml.stub`:**
- Add `cache: '%%PACKAGE_MANAGER%%'` to the `actions/setup-node@v4` `with` block
- Add Composer cache steps (get cache dir + `actions/cache@v4`) after PHP setup, before `composer install`

### Workflow diff (conceptual)

```yaml
# After Setup PHP, before Install Composer dependencies:
- name: Get Composer cache directory
  id: composer-cache
  run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

- name: Cache Composer dependencies
  uses: actions/cache@v4
  with:
    path: ${{ steps.composer-cache.outputs.dir }}
    key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
    restore-keys: ${{ runner.os }}-composer-

# Setup Node.js with cache:
- name: Setup Node.js
  uses: actions/setup-node@v4
  with:
    node-version: ${{ env.NODE_VERSION }}
    cache: ${{ env.PACKAGE_MANAGER }}
```

### Tests
- Unit test: verify generated workflow contains cache steps
- Unit test: verify `cache:` value matches selected package manager (npm or yarn)

---

## W2: env_mapping + env_static + Sed Escaping

### What
Wire `env_mapping` and `env_static` from `netsons-deploy.json` into the generated workflow. Add proper sed escaping for special characters in secret values.

### Changes

**`stubs/workflows/deploy.yml.stub`:**
- The "Update .env values" step becomes a template. The `InstallCommand` reads `netsons-deploy.json` and generates:
  - For each `env_mapping` entry: an `env:` line referencing `${{ secrets.SECRET_NAME }}` and a sed command
  - For each `env_static` entry: a hardcoded sed command
  - All sed commands use a sed-safe escaping approach

**Sed escaping approach:**
Use `|` as delimiter and escape `|`, `&`, `\` in values:

```yaml
# In the workflow, for secret-backed values:
ESCAPED_VALUE=$(printf '%s' "${DB_PASSWORD}" | sed 's/[|&\\]/\\&/g')
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${ESCAPED_VALUE}|" ${ENV_FILE}
```

For static values (known at generation time), escape them when writing the workflow.

**`src/Commands/InstallCommand.php`:**
- `publishWorkflow()` reads `netsons-deploy.json` and replaces a `%%ENV_UPDATES%%` placeholder block in the stub with generated sed commands
- Generates proper `env:` block with secret references

**`stubs/workflows/deploy.yml.stub`:**
- The "Update .env values" step uses a `%%ENV_UPDATES_ENV_BLOCK%%` and `%%ENV_UPDATES_SED_BLOCK%%` placeholder

### Template example (generated output)

```yaml
- name: Update .env values
  env:
    SSH_HOST: ${{ vars.SSH_HOST }}
    SSH_PORT: ${{ vars.SSH_PORT || '65100' }}
    SSH_USER: ${{ vars.SSH_USER }}
    DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
    APP_ENV: ${{ vars.APP_ENV }}
    APP_DEBUG: ${{ vars.APP_DEBUG }}
    APP_URL: ${{ vars.APP_URL }}
    DB_DATABASE: ${{ secrets.DB_DATABASE }}
    DB_USERNAME: ${{ secrets.DB_USERNAME }}
    DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
  run: |
    ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<REMOTE
      set -euo pipefail
      ENV_FILE=~/${{ vars.DEPLOY_PATH }}/shared/.env
      # Core variables
      sed -i "s/^APP_ENV=.*/APP_ENV=${APP_ENV}/" ${ENV_FILE}
      sed -i "s/^APP_DEBUG=.*/APP_DEBUG=${APP_DEBUG}/" ${ENV_FILE}
      sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" ${ENV_FILE}
      # Secret-backed variables (escaped for sed)
      ESCAPED=$(printf '%s' "${DB_DATABASE}" | sed 's/[|&\\]/\\&/g')
      sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${ESCAPED}|" ${ENV_FILE}
      ESCAPED=$(printf '%s' "${DB_USERNAME}" | sed 's/[|&\\]/\\&/g')
      sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${ESCAPED}|" ${ENV_FILE}
      ESCAPED=$(printf '%s' "${DB_PASSWORD}" | sed 's/[|&\\]/\\&/g')
      sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${ESCAPED}|" ${ENV_FILE}
      # Static variables
      sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=database|" ${ENV_FILE}
      sed -i "s|^LARAVEL_PDF_DRIVER=.*|LARAVEL_PDF_DRIVER=dompdf|" ${ENV_FILE}
    REMOTE
```

### Tests
- Unit test: verify env_mapping entries generate correct sed commands with escaping
- Unit test: verify env_static entries generate hardcoded sed commands
- Unit test: verify empty env_mapping/env_static produces only core variables (APP_ENV, APP_DEBUG, APP_URL)
- Unit test: sed escaping helper handles `&`, `/`, `\`, `|` characters

---

## W3: key:generate on First Deploy

### What
Add `php artisan key:generate --force` on first deploy, after shared resources setup, before .env updates.

### Changes

**`stubs/workflows/deploy.yml.stub`:**
Add step between "Setup shared resources" and "Update .env values":

```yaml
- name: Generate app key on first deploy
  env:
    SSH_HOST: ${{ vars.SSH_HOST }}
    SSH_PORT: ${{ vars.SSH_PORT || '65100' }}
    SSH_USER: ${{ vars.SSH_USER }}
    DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
  run: |
    FIRST_DEPLOY=$(ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} \
      "[ -f ~/${{ vars.DEPLOY_PATH }}/.first_deploy ] && echo yes || echo no")
    if [ "$FIRST_DEPLOY" = "yes" ]; then
      ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<REMOTE
        set -euo pipefail
        cd ~/${{ vars.DEPLOY_PATH }}/releases/${{ steps.release.outputs.dir }}
        ${{ env.REMOTE_PHP }} artisan key:generate --force
      REMOTE
    fi
```

### Tests
- Unit test: verify generated workflow contains key:generate step
- Unit test: verify step checks .first_deploy flag

---

## W4: First-Deploy Seeders + .first_deploy Cleanup

### What
Run configured seeders on first deploy. Remove `.first_deploy` flag after seeders complete.

### Changes

**`stubs/workflows/deploy.yml.stub`:**
Add step after "Run migrations":

```yaml
- name: Run seeders on first deploy
  env:
    SSH_HOST: ${{ vars.SSH_HOST }}
    SSH_PORT: ${{ vars.SSH_PORT || '65100' }}
    SSH_USER: ${{ vars.SSH_USER }}
    DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
  run: |
    ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<REMOTE
      set -euo pipefail
      cd ~/${{ vars.DEPLOY_PATH }}/releases/${{ steps.release.outputs.dir }}
      if [ -f ~/${{ vars.DEPLOY_PATH }}/.first_deploy ]; then
        %%SEEDERS%%
        rm ~/${{ vars.DEPLOY_PATH }}/.first_deploy
      fi
    REMOTE
```

**`src/Commands/InstallCommand.php`:**
- Read `seeders` from `config/netsons-deploy.php`
- Replace `%%SEEDERS%%` with generated seeder commands, e.g.:
  `${{ env.REMOTE_PHP }} artisan db:seed --class=RolesAndPermissionsSeeder --force`
- If no seeders configured, still include the step but only remove `.first_deploy` flag

### Tests
- Unit test: verify seeders generate correct artisan commands
- Unit test: verify .first_deploy removal is always present
- Unit test: verify step is skipped gracefully when no .first_deploy exists

---

## W5: SSH Cleanup Step

### What
Add `if: always()` SSH cleanup step at the end of the workflow.

### Changes

**`stubs/workflows/deploy.yml.stub`:**
Replace the "Deployment complete" step or add after it:

```yaml
- name: Cleanup SSH
  if: always()
  run: |
    rm -f $HOME/.ssh/deploy_key /tmp/askpass.sh
    [ -n "$SSH_AGENT_PID" ] && kill $SSH_AGENT_PID || true
```

### Tests
- Unit test: verify generated workflow contains cleanup step with `if: always()`

---

## W6: Custom Post-Deploy Artisan Commands

### What
Support custom artisan commands in the "Rebuild caches" step. Add `package:discover --ansi` to defaults.

### Changes

**`netsons-deploy.json` schema:**
```json
{
  "custom_commands": [
    "event-sourcing:cache-event-handlers 2>/dev/null || true"
  ]
}
```

**`stubs/workflows/deploy.yml.stub`:**
- Add `package:discover --ansi` before the cache commands (always present)
- Add `%%CUSTOM_COMMANDS%%` placeholder after cache commands, before `queue:restart`

**`src/Commands/InstallCommand.php`:**
- Read `custom_commands` from `netsons-deploy.json`
- Replace `%%CUSTOM_COMMANDS%%` with generated lines:
  `${{ env.REMOTE_PHP }} artisan <command>`

### Generated output example

```yaml
- name: Rebuild caches
  run: |
    ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<REMOTE
      set -euo pipefail
      cd ~/${{ vars.DEPLOY_PATH }}/releases/${{ steps.release.outputs.dir }}
      ${{ env.REMOTE_PHP }} artisan cache:clear
      ${{ env.REMOTE_PHP }} artisan package:discover --ansi
      ${{ env.REMOTE_PHP }} artisan config:cache
      ${{ env.REMOTE_PHP }} artisan route:cache
      ${{ env.REMOTE_PHP }} artisan view:cache
      ${{ env.REMOTE_PHP }} artisan event:cache
      ${{ env.REMOTE_PHP }} artisan event-sourcing:cache-event-handlers 2>/dev/null || true
      ${{ env.REMOTE_PHP }} artisan queue:restart 2>/dev/null || true
    REMOTE
```

### Documentation
- Document common custom commands with examples:
  - Spatie Event Sourcing: `event-sourcing:cache-event-handlers 2>/dev/null || true`
  - Spatie Permission: `permission:cache-reset`
  - Horizon: `horizon:terminate`
  - Telescope: `telescope:prune`
  - Scout: `scout:sync-index-settings`

### Tests
- Unit test: verify package:discover is always present
- Unit test: verify custom commands are injected in correct position
- Unit test: verify empty custom_commands produces no extra lines

---

## W7: Build Env Vars

### What
Support custom environment variables during the `yarn build` / `npm run build` step (e.g., `VITE_APP_NAME`).

### Changes

**`netsons-deploy.json` schema:**
```json
{
  "build_env": {
    "VITE_APP_NAME": "My App Name"
  }
}
```

**`stubs/workflows/deploy.yml.stub`:**
- Add `%%BUILD_ENV%%` placeholder in the "Build assets" step `env:` block

**`src/Commands/InstallCommand.php`:**
- Read `build_env` from `netsons-deploy.json`
- Replace `%%BUILD_ENV%%` with generated env lines

### Generated output example

```yaml
- name: Build assets
  env:
    VITE_APP_NAME: "My App Name"
  run: |
    if [ "${{ env.PACKAGE_MANAGER }}" = "yarn" ]; then
      yarn build
    else
      npm run build
    fi
```

### Tests
- Unit test: verify build_env generates env block
- Unit test: verify empty build_env produces no env block

---

## W8: Slack Notifications (Opt-in)

### What
Add optional Slack notification steps for deploy success/failure.

### Changes

**`netsons-deploy.json` schema:**
```json
{
  "notifications": {
    "slack_webhook_secret": "SLACK_WEBHOOK_DEBUG"
  }
}
```

**`stubs/workflows/deploy.yml.stub`:**
- Add `%%NOTIFICATIONS%%` placeholder near the end (before SSH cleanup)
- Only generated when `notifications.slack_webhook_secret` is set

**`src/Commands/InstallCommand.php`:**
- Read notifications config from `netsons-deploy.json`
- Replace `%%NOTIFICATIONS%%` with success/failure curl steps

### Generated output

```yaml
- name: Notify Slack on success
  if: success()
  env:
    SLACK_WEBHOOK: ${{ secrets.SLACK_WEBHOOK_DEBUG }}
  run: |
    if [ -n "$SLACK_WEBHOOK" ]; then
      curl -s -X POST -H 'Content-type: application/json' \
        --data '{"text":":white_check_mark: Deploy ${{ github.event.inputs.environment }} succeeded — release ${{ steps.release.outputs.dir }}"}' \
        "$SLACK_WEBHOOK"
    fi

- name: Notify Slack on failure
  if: failure()
  env:
    SLACK_WEBHOOK: ${{ secrets.SLACK_WEBHOOK_DEBUG }}
  run: |
    if [ -n "$SLACK_WEBHOOK" ]; then
      curl -s -X POST -H 'Content-type: application/json' \
        --data '{"text":":x: Deploy ${{ github.event.inputs.environment }} failed — ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}"}' \
        "$SLACK_WEBHOOK"
    fi
```

### Tests
- Unit test: verify Slack steps generated when webhook configured
- Unit test: verify no Slack steps when notifications not configured
- Unit test: verify secret name is correctly referenced

---

## W9: FTP Root Path Config

### What
Add `ftp.root_path` to config so users can specify the FTP account's root directory. This affects the `server-dir` in the FTP deploy step.

### Problem
FTP accounts on Netsons can have different root directories:
- `/home/user/` (shared FTP account, accesses all sites)
- `/home/user/mywebsite.com/` (dedicated FTP account, scoped to one site)

The current workflow assumes FTP root = home directory, so `server-dir` = `DEPLOY_PATH/releases/...`. If the FTP root is already the site directory, the path doubles up.

### Changes

**`config/netsons-deploy.php`:**
```php
'ftp' => [
    'host' => env('NETSONS_FTP_HOST'),
    'port' => env('NETSONS_FTP_PORT', 21),
    'user' => env('NETSONS_FTP_USER'),
    'password' => env('NETSONS_FTP_PASS'),
    'protocol' => env('NETSONS_FTP_PROTOCOL', 'ftp'),
    'root_path' => env('NETSONS_FTP_ROOT_PATH', ''),  // NEW
],
```

**`stubs/workflows/deploy.yml.stub`:**
- Change FTP deploy `server-dir` to use a computed path:
  - If `ftp.root_path` is empty (default): `server-dir: ${{ vars.DEPLOY_PATH }}/releases/...`
  - If `ftp.root_path` is set: `server-dir: releases/...` (DEPLOY_PATH already covered by FTP root)

**`src/Commands/InstallCommand.php`:**
- During install, ask: "What is the FTP account root directory?" with options:
  - Home directory (`/home/user/`) — default
  - Site directory (`/home/user/site.com/`) — DEPLOY_PATH is stripped from server-dir
  - Custom path
- Store the choice and compute the correct `server-dir` in the workflow

### Documentation
- Explain the FTP root path concept in `docs/ftp-strategy.md`
- Add troubleshooting entry for "files uploaded to wrong directory"
- Show how to check/change FTP root in Netsons cPanel

### Tests
- Unit test: verify server-dir with empty root_path (default)
- Unit test: verify server-dir with site-scoped root_path

---

## W10: `netsons:env` Command

### What
New artisan command for managing env variable configuration in `netsons-deploy.json` after initial install.

### Signature

```php
protected $signature = 'netsons:env
                        {action? : Action to perform (list, add, remove)}';
```

### Subcommands

**`php artisan netsons:env`** (or `netsons:env list`):
- Reads `netsons-deploy.json`
- Shows table of all configured env variables grouped by type:
  - Secret-backed (env_mapping)
  - Static (env_static)
  - Build (build_env)
  - Custom commands
  - Notifications

**`php artisan netsons:env add`:**
- Interactive flow:
  1. "What type?" — choice: secret-backed / static / build
  2. "ENV variable name?" — text input (e.g., `DB_PASSWORD`)
  3. If secret-backed: "GitHub Secret name?" — text input, defaults to same as env name
  4. If static: "Value?" — text input (e.g., `dompdf`)
  5. If build: "Value?" — text input (e.g., `My App`)
- Writes to `netsons-deploy.json`
- Asks: "Regenerate deploy.yml?" — if yes, regenerates the workflow

**`php artisan netsons:env remove`:**
- Shows numbered list of all configured variables
- User picks which to remove
- Updates `netsons-deploy.json`
- Asks: "Regenerate deploy.yml?"

### File: `src/Commands/EnvCommand.php`

### Tests
- Feature test: `netsons:env list` shows configured variables
- Feature test: `netsons:env add` with secret-backed variable
- Feature test: `netsons:env add` with static variable
- Feature test: `netsons:env remove` removes variable
- Feature test: verify `netsons-deploy.json` is updated correctly

---

## W11: netsons-deploy.json Integration

### What
Add `netsons-deploy.json` file management as part of the install flow and wire it into workflow generation.

### Changes

**`src/Commands/InstallCommand.php`:**
- After strategy selection and before workflow generation, add interactive env setup:
  1. "Do you want to configure custom .env variables?" (y/n)
  2. If yes, enter loop (same as `netsons:env add` flow)
  3. "Do you want to configure build environment variables?" (y/n)
  4. "Do you want to add custom post-deploy artisan commands?" (y/n)
  5. "Do you want Slack deploy notifications?" (y/n) — if yes, ask for secret name
- Write `netsons-deploy.json` to project root
- `publishWorkflow()` reads the JSON and replaces all placeholders

**New service class: `src/Services/DeployConfigManager.php`:**
- `read(): array` — reads `netsons-deploy.json`, returns parsed data with defaults
- `write(array $data): void` — writes to `netsons-deploy.json`
- `addEnvMapping(string $key, string $secret): void`
- `addEnvStatic(string $key, string $value): void`
- `addBuildEnv(string $key, string $value): void`
- `addCustomCommand(string $command): void`
- `removeEntry(string $type, string $key): void`
- `setSlackWebhook(?string $secretName): void`

Shared between `InstallCommand` and `EnvCommand`.

### Tests
- Unit test: DeployConfigManager read/write
- Unit test: DeployConfigManager add/remove operations
- Unit test: default values when JSON doesn't exist

---

## Implementation Order

Phase 1 — Foundation:
1. **W11** — DeployConfigManager service + netsons-deploy.json schema
2. **W2** — env_mapping/env_static wiring + sed escaping

Phase 2 — Workflow stub fixes:
3. **W1** — Dependency caching
4. **W3** — key:generate on first deploy
5. **W4** — Seeders + .first_deploy cleanup
6. **W5** — SSH cleanup

Phase 3 — Enhancements:
7. **W6** — Custom post-deploy commands
8. **W7** — Build env vars
9. **W8** — Slack notifications
10. **W9** — FTP root path

Phase 4 — Command UX:
11. **W10** — `netsons:env` command
12. Update `netsons:install` to use interactive env setup (W11 integration)
13. Update `netsons:check` to show netsons-deploy.json status

Phase 5 — Documentation:
14. Update `docs/` markdown files
15. Update `website/src/content/docs/` pages
16. Update `README.md`

---

## Files to Create

| File | Purpose |
|------|---------|
| `src/Services/DeployConfigManager.php` | JSON config read/write/manage |
| `src/Commands/EnvCommand.php` | `netsons:env` command |
| `tests/Unit/DeployConfigManagerTest.php` | Unit tests |
| `tests/Feature/EnvCommandTest.php` | Feature tests |

## Files to Modify

| File | Changes |
|------|---------|
| `config/netsons-deploy.php` | Add `ftp.root_path` |
| `stubs/workflows/deploy.yml.stub` | Add caching, placeholders, SSH cleanup, key:generate, seeders |
| `src/Commands/InstallCommand.php` | Read JSON, replace placeholders, interactive env setup |
| `src/Commands/CheckCommand.php` | Show netsons-deploy.json status |
| `src/NetsonsDeployServiceProvider.php` | Register `netsons:env` command |
| `src/Strategies/FtpStrategy.php` | Add secrets from env_mapping to requiredSecrets() |
| `src/Strategies/GitStrategy.php` | Add secrets from env_mapping to requiredSecrets() |
| `docs/configuration.md` | Document netsons-deploy.json, env_mapping, env_static |
| `docs/ftp-strategy.md` | Document FTP root path |
| `docs/troubleshooting.md` | Add FTP root path troubleshooting |
| `README.md` | Update with new features |
| `website/src/content/docs/` | Mirror doc updates |

## Existing Tests to Update

| File | Changes |
|------|---------|
| `tests/Feature/InstallCommandTest.php` | Test interactive env setup, JSON generation |
| `tests/Feature/CheckCommandTest.php` | Test JSON status display |
| `tests/Unit/ConfigTest.php` | Test ftp.root_path default |
