# Plan: First Deployment Improvements

**Status: COMPLETED** (2026-05-14)

## Problem

The first-deployment feature (`.first_deploy` sentinel file, app key generation, seeders) is implemented and working but has several gaps:

1. **No interactive seeder configuration during install** — Users must manually edit `config/netsons-deploy.php` to add seeders. The `netsons:install` command doesn't prompt for them.
2. **No seeder display in `netsons:check`** — The check command doesn't report configured seeders or first-deploy settings.
3. **No seeder class validation** — Workflow is generated with seeder class names that may not exist.
4. **No dedicated website documentation page** — `docs/first-deployment.md` exists but there is no corresponding `.mdx` page in the documentation website.

## Approach

Address each gap as a self-contained improvement. All changes are additive and low-risk.

## Implementation Details

### 1. Interactive seeder configuration in `netsons:install`

**File:** `src/Commands/InstallCommand.php`

Add a prompt during the install flow (after post-deploy options) asking the user if they want to configure seeders for the first deployment:

- Use `confirm()` to ask "Do you want to configure seeders for the first deployment?"
- If yes, use `text()` to collect comma-separated seeder class names (e.g. `RoleSeeder, PermissionSeeder`)
- Store the list in the config/workflow generation pipeline
- Validate that class names look like valid PHP class names (alphanumeric + backslash for namespaces)

### 2. Display seeders in `netsons:check`

**File:** `src/Commands/CheckCommand.php`

Add a section to the check output showing:
- Configured seeders (from config)
- Whether the list is empty (show a note that no seeders are configured)

### 3. Seeder class name validation

**File:** `src/Commands/InstallCommand.php`

When seeders are provided during install:
- Validate each name matches a PHP class name pattern (`/^[A-Za-z_\\][A-Za-z0-9_\\]*$/`)
- Warn (but don't block) if the class doesn't exist in the project — the user may add it later

### 4. Website documentation page

**File:** `website/src/content/docs/guides/first-deployment.mdx` (new)

Create a dedicated guide page covering:
- How the `.first_deploy` sentinel works
- App key generation on first deploy
- Seeder configuration and execution
- How to re-run seeders manually after first deploy
- Troubleshooting first-deploy issues

Update the Starlight sidebar config to include the new page.

## Files to modify

1. **Edit:** `src/Commands/InstallCommand.php` — add seeder prompts and validation
2. **Edit:** `src/Commands/CheckCommand.php` — display configured seeders
3. **New:** `website/src/content/docs/guides/first-deployment.mdx` — website documentation
4. **Edit:** `website/astro.config.mjs` — add sidebar entry for new page
5. **Edit:** `tests/Feature/InstallCommandTest.php` — tests for seeder prompts
6. **Edit:** `tests/Feature/CheckCommandTest.php` — tests for seeder display

## Scope

Small to medium. The core changes (install prompts + check display) are straightforward. The website page is mostly content.

## Risk

Low. All changes are additive. The existing first-deploy flow is untouched — this only improves the DX around configuring it.

## Implementation Notes

**Approach:** Seeders moved from config-only to `netsons-deploy.json` (with config fallback). This aligns with how all other interactive configuration (env mappings, custom commands, notifications) is stored.

**Key decisions:**
- Seeders in `netsons-deploy.json` take precedence over `config/netsons-deploy.php` when both are set
- Class name validation uses regex `/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/` — warns but doesn't block
- Existing seeders are restored during reconfiguration (same pattern as custom commands)
- `netsons:check` shows seeders in the deploy JSON table with type "Seeder" and note "First deploy only"
- Smart seeder detection: `detectSeeders()` reads `composer.json` and always suggests `DatabaseSeeder`, plus package-specific seeders (e.g., `RoleSeeder` and `PermissionSeeder` when `spatie/laravel-permission` is detected)
- `collectSeeders()` uses multiselect with detected seeders as options, plus manual entry fallback for additional seeders
- Default examples use `DatabaseSeeder` (Laravel default), not Spatie-specific seeders

**Files modified:**
- `src/Services/DeployConfigManager.php` — added `seeders` to DEFAULTS, `addSeeder()`, `removeSeeder()`, `detectSeeders()`
- `src/Commands/InstallCommand.php` — added `collectSeeders()` with multiselect + detection, `collectManualSeeders()`, reads seeders from JSON with config fallback
- `src/Commands/CheckCommand.php` — displays seeders in deploy JSON table, shows "No seeders configured" when empty
- `website/src/content/docs/guides/first-deployment.mdx` — new guide page with detection table
- `website/astro.config.mjs` — added Guides sidebar section
- `docs/configuration.md` — updated seeders section and JSON schema to use DatabaseSeeder
- `website/src/content/docs/reference/configuration.mdx` — updated seeders section and JSON schema
- `README.md` — added seeders to netsons-deploy.json feature list
- `CLAUDE.md` — updated seeder example to DatabaseSeeder
- `tests/Unit/DeployConfigManagerTest.php` — 14 new tests (8 seeder CRUD + 6 detectSeeders)
- `tests/Feature/InstallCommandTest.php` — 3 new tests for workflow generation from JSON seeders
- `tests/Feature/CheckCommandTest.php` — 3 new tests for seeder display

**Test results:** 277 tests passed (600 assertions), lint passed.
