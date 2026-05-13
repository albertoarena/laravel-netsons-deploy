# Plan: Make remote Composer path configurable

**Origin:** Git strategy deploy fails with `Could not open input file: /opt/cpanel/composer/bin/composer` because the Composer path varies across Netsons plans.

**Date:** 2026-05-13

---

## Problem

The git deploy step hardcodes the Composer path as `/opt/cpanel/composer/bin/composer`. On some Netsons servers, Composer is at `/usr/local/bin/composer` instead. The path varies by hosting plan and setup.

The FTP strategy is unaffected because it runs Composer on the GitHub Actions runner (where Composer is always available at `composer`).

## Fix

Add a configurable `REMOTE_COMPOSER` path, defaulting to `/opt/cpanel/composer/bin/composer` for backwards compatibility.

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
