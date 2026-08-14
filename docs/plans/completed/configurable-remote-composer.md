# Plan: Make remote Composer path configurable

**Origin:** Git strategy deploy fails with `Could not open input file: /opt/cpanel/composer/bin/composer` because the Composer path varies across Netsons plans.

**Date:** 2026-05-13

---

## Problem

The git deploy step hardcodes the Composer path as `/opt/cpanel/composer/bin/composer`. On some Netsons servers, Composer is at `/usr/local/bin/composer` instead. The path varies by hosting plan and setup.

The FTP strategy is unaffected because it runs Composer on the GitHub Actions runner (where Composer is always available at `composer`).

## Fix

Add a configurable `REMOTE_COMPOSER` path, defaulting to `/usr/local/bin/composer` (the standard Netsons path).

### Changes

**`stubs/workflows/deploy.yml.stub`:**
- Add `REMOTE_COMPOSER` to the env block at the top (alongside `REMOTE_PHP`), reading from `${{ vars.REMOTE_COMPOSER }}` with a default
- Use `${REMOTE_COMPOSER}` in the git deploy step instead of the hardcoded path

**`action.yml`:**
- Add `remote-composer` input with default `/opt/cpanel/composer/bin/composer`
- Use it in the deploy step

**`config/netsons-deploy.php`:**
- Add `composer_binary` config key

**`src/Commands/InstallCommand.php`:**
- Add prompt for Composer path during interactive install

**`tests/Feature/InstallCommandTest.php`:**
- Assert `REMOTE_COMPOSER` appears in generated workflow

### Files to change

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Add `REMOTE_COMPOSER` env var, use in git deploy step |
| `action.yml` | Add `remote-composer` input, use in git deploy step |
| `config/netsons-deploy.php` | Add `composer_binary` config key |
| `src/Commands/InstallCommand.php` | Add Composer path prompt |
| `tests/Feature/InstallCommandTest.php` | Assert `REMOTE_COMPOSER` in workflow |

---

## Update: Create bootstrap/cache before composer install (2026-05-13)

### Problem

After git clone, `composer install` triggers `post-autoload-dump` which runs `artisan package:discover`. This fails because `bootstrap/cache` doesn't exist in the freshly cloned repo (it's gitignored).

```
The bootstrap/cache directory must be present and writable.
```

The FTP strategy is unaffected because `bootstrap/cache` is created in the "Prepare Laravel directories" step on the runner before Composer runs.

### Fix

Add `mkdir -p bootstrap/cache` before `composer install` in the git deploy SSH command. This ensures the directory exists when Composer's post-install scripts run.

```bash
ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} \
  "cd ~/${DEPLOY_PATH}/releases/${RELEASE_DIR} && mkdir -p bootstrap/cache && ${REMOTE_PHP} ${REMOTE_COMPOSER} install ..."
```

### Files to change

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Add `mkdir -p bootstrap/cache` before `composer install` in git deploy step |
| `action.yml` | Same |
| `tests/Feature/InstallCommandTest.php` | Assert `mkdir -p bootstrap/cache` in git deploy step |
