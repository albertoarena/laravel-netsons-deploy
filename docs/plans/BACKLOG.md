# Backlog

---

## B1: Adopt Laravel Prompts for interactive commands

**Priority:** Enhancement
**Status:** DONE
**Date added:** 2026-05-10
**Date completed:** 2026-05-10

### What

Replace traditional `$this->choice()`, `$this->confirm()`, `$this->ask()`, `$this->table()`, `$this->info()` calls with [Laravel Prompts](https://laravel.com/docs/13.x/prompts) for a modern, polished CLI experience.

Laravel Prompts provides:
- `select()` / `multiselect()` — replaces `$this->choice()` with arrow-key navigation
- `text()` — replaces `$this->ask()` with inline validation and placeholder support
- `confirm()` — replaces `$this->confirm()` with styled yes/no
- `suggest()` — autocomplete text input (useful for custom commands)
- `spin()` — spinner for long-running operations
- `table()` — styled table output
- `note()` / `info()` / `warning()` / `error()` — styled output blocks

### Affected commands

| Command | Current methods | Prompts replacement |
|---|---|---|
| `netsons:install` | `choice()`, `confirm()`, `ask()`, `info()`, `table()` | `select()`, `confirm()`, `text()`, `note()`, `table()` |
| `netsons:env` | `choice()`, `ask()`, `confirm()`, `info()`, `table()` | `select()`, `text()`, `confirm()`, `note()`, `table()` |
| `netsons:check` | `info()`, `warn()`, `table()` | `note()`, `warning()`, `table()` |

### Compatibility analysis

**Package minimum:** Laravel 10 (`illuminate/console: ^10.0|^11.0|^12.0|^13.0`)

`laravel/prompts` was introduced as a dependency of `illuminate/console` starting at **Laravel 10.17**. This means:

| Laravel version | laravel/prompts available | Notes |
|---|---|---|
| 10.0–10.16 | No | Not bundled, not installed |
| 10.17+ | Yes | Bundled as a dependency of illuminate/console |
| 11.x | Yes | Always available |
| 12.x | Yes | Always available |
| 13.x | Yes | Always available |

### Options

**Option A: Add as a direct dependency**

```json
"require": {
    "laravel/prompts": "^0.1.0|^0.2.0|^0.3.0"
}
```

- Works on all supported Laravel versions
- Adds an explicit dependency (but it's already installed for most users)
- Cleanest approach

**Option B: Conditional usage**

```php
if (class_exists(\Laravel\Prompts\Prompt::class)) {
    // Use Laravel Prompts
} else {
    // Fall back to Illuminate Command methods
}
```

- No new dependency
- Maintains backward compatibility
- Code duplication (two paths per prompt)
- Harder to test

**Option C: Raise minimum to Laravel 10.17**

```json
"require": {
    "illuminate/console": "^10.17|^11.0|^12.0|^13.0"
}
```

- No new dependency, no conditional code
- Drops support for Laravel 10.0–10.16 (released March–July 2023)
- These versions are well past their support lifecycle

### Recommendation

**Option A** is recommended. Adding `laravel/prompts` as a direct dependency:
- Explicit and clear
- No conditional code paths
- Maintains full Laravel 10+ compatibility
- The package is lightweight (~50KB)
- Already installed transitively for 99%+ of users

### Implementation notes

- Non-interactive mode (`--no-interaction`) must still work — Laravel Prompts gracefully falls back when `STDIN` is not interactive
- Tests using `expectsChoice()`, `expectsQuestion()`, `expectsConfirmation()` may need updates — check Pest/Testbench compatibility with Prompts
- The `suggest()` function could improve `netsons:env add` by suggesting common env variable names (e.g., `DB_PASSWORD`, `DB_DATABASE`, `MAIL_HOST`)
- `spin()` could wrap the workflow generation step for visual feedback

---

## B2: Improve interactive env setup UX

**Priority:** UX fix
**Status:** DONE
**Date added:** 2026-05-10
**Date completed:** 2026-05-10

### Problem

The `netsons:install` interactive env setup prompt is confusing:

```
Add secret-backed .env variables (from GitHub Secrets)? (yes/no) [no]:
```

Two issues:

1. **No context on what's already handled.** The user sees their GitHub Secrets list (e.g., `FTP_HOST`, `SSH_PRIVATE_KEY`, `DB_DATABASE`) but can't tell which ones are already wired into the workflow by the strategy. Infrastructure secrets (`SSH_*`, `FTP_*`) are built-in — the prompt is only about additional project-specific secrets (DB credentials, API keys, etc.), but nothing says that.

2. **No duplicate detection.** If the user adds a secret that already exists in `netsons-deploy.json` (e.g., runs `netsons:env add` and types `DB_DATABASE` when it's already configured), it silently overwrites. It should warn and skip.

### Changes

#### Show already-handled secrets before prompting

Before asking about additional secrets, display what's already wired in:

```
  The following secrets are already configured by the FTP strategy:
  SSH_PRIVATE_KEY, SSH_KNOWN_HOSTS, SSH_KEY_PASSPHRASE, FTP_HOST, FTP_USER, FTP_PASS, FTP_PORT

  Add additional .env variables from GitHub Secrets? (e.g., DB_PASSWORD, DB_USERNAME)
  (yes/no) [no]:
```

For git strategy, show: `SSH_PRIVATE_KEY, SSH_KNOWN_HOSTS, SSH_KEY_PASSPHRASE`

This makes it clear that FTP/SSH secrets don't need to be added — only project-specific ones.

#### Suggest common env variable names

When adding secret-backed variables, suggest common names:

```
  ENV variable name (e.g., DB_PASSWORD):
```

With Laravel Prompts (B1), this could use `suggest()` with a list like:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST`, `DB_PORT`
- `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- `REDIS_HOST`, `REDIS_PASSWORD`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`

#### Duplicate detection

In both `netsons:install` and `netsons:env add`, when the user enters a key that already exists:

```
  ENV variable name: DB_DATABASE
  "DB_DATABASE" is already configured (secret: DB_DATABASE). Skipping.
```

For `netsons:env add`, offer to update instead:

```
  ENV variable name: DB_DATABASE
  "DB_DATABASE" is already configured (secret: DB_DATABASE). Update it? (yes/no) [no]:
```

### Affected files

| File | Changes |
|------|---------|
| `src/Commands/InstallCommand.php` | Show strategy secrets before prompt, reword prompt, add duplicate check |
| `src/Commands/EnvCommand.php` | Add duplicate check with update option |
| `src/Services/DeployConfigManager.php` | Add `hasEnvMapping(string $key): bool` helper |
| `src/Strategies/FtpStrategy.php` | Already has `requiredSecrets()` — reuse |
| `src/Strategies/GitStrategy.php` | Already has `requiredSecrets()` — reuse |
| `tests/Feature/InstallCommandTest.php` | Test duplicate warning |
| `tests/Feature/EnvCommandTest.php` | Test duplicate detection and update flow |

### Dependency

Can be implemented independently of B1 (Laravel Prompts), but B1 would enhance the UX further with `suggest()` for common env names.

---

## B3: Adopt Laravel Pint for code styling

**Priority:** Enhancement
**Status:** DONE
**Date added:** 2026-05-10
**Date completed:** 2026-05-10

### What

Add [Laravel Pint](https://laravel.com/docs/13.x/pint) as the code style fixer for the project. Pint is an opinionated PHP code style fixer built on top of PHP-CS-Fixer, configured for Laravel's coding conventions out of the box.

### Changes

1. **Add as dev dependency:**
   ```bash
   composer require laravel/pint --dev
   ```

2. **Add `pint.json`** (project root) for any project-specific overrides. Default Laravel preset should work since the project already follows PSR-12 + Laravel conventions.

3. **Add composer scripts:**
   ```json
   "scripts": {
       "lint": "pint --test",
       "lint:fix": "pint"
   }
   ```

4. **Run Pint** on the entire codebase to fix any existing style issues.

5. **Update CLAUDE.md** — add note that Laravel Pint must be used for code styling and that `composer lint` should pass before committing.

6. **CI integration** — optionally add a Pint check step to the test workflow or a GitHub Actions workflow for style checks.

### Implementation notes

- Run `pint` once to normalize the entire codebase, commit as a standalone formatting commit
- After the initial pass, Pint should be run before each commit to keep style consistent
- Consider adding a pre-commit hook or CI check to enforce style

---

## B4: Evaluate dropping PHP 8.2 minimum

**Priority:** Low
**Status:** Deferred until Dec 2026
**Date added:** 2026-05-10

### Analysis (June 2025 data)

**PHP version usage (Packagist installs, June 2025):**

| Version | Share |
|---------|-------|
| PHP 8.4 | 13.7% |
| PHP 8.3 | 34.0% |
| PHP 8.2 | 24.8% |
| PHP 8.1 | 13.4% |

**Laravel PHP requirements:**

| Laravel | Min PHP | Status |
|---------|---------|--------|
| 10 | 8.1 | End-of-life |
| 11 | 8.2 | Security support ended March 2026 |
| 12 | 8.2 | Current release |
| 13 | 8.3 | Latest |

**PHP 8.2 lifecycle:**
- Active support (bug fixes): ended December 2024
- Security support: ends December 2026

### Conclusion

PHP 8.2 support remains necessary. Laravel 12 (current mainline) requires 8.2 as its minimum, and ~25% of the Packagist ecosystem runs on it. Dropping 8.2 would lock out Laravel 12 users who haven't upgraded PHP yet.

**Revisit after December 2026** when PHP 8.2 security support ends. At that point, raising the floor to PHP 8.3 would be reasonable — it aligns with Laravel 13's minimum and enables use of PHP 8.3 features (typed class constants, `json_validate()`, `#[Override]` attribute, etc.).

### Impact on tooling

- `pint.json` is configured with `"php_version": "8.2"` to prevent Pint from introducing PHP 8.3+ syntax
- When PHP 8.2 is dropped, update `pint.json` and `composer.json` accordingly

### Sources

- [PHP version stats: June 2025 — Stitcher.io](https://stitcher.io/blog/php-version-stats-june-2025)
- [PHP Supported Versions — php.net](https://www.php.net/supported-versions.php)
- [PHP Migration Trends — Zend](https://www.zend.com/blog/php-migration-trends)

---

## B5: Auto-detect common env variables from .env.example

**Priority:** Enhancement
**Status:** DONE
**Date added:** 2026-05-10
**Date completed:** 2026-05-10

### Problem

During `netsons:install`, users must manually type every env variable name (e.g., `DB_DATABASE`, `DB_PASSWORD`). Most Laravel apps need the same common set, and the project's `.env.example` already lists all of them.

### Proposal

During the interactive env setup in `netsons:install`, read `.env.example` and auto-suggest common variables:

**Secret-backed (from GitHub Secrets) — pre-checked:**
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `DB_HOST`, `DB_PORT` (if non-default values)
- `MAIL_USERNAME`, `MAIL_PASSWORD` (if present)
- `REDIS_PASSWORD` (if present)

**Static (fixed values) — detected from .env.example:**
- `SESSION_DRIVER` (if set to something other than `file`)
- Other app-specific keys with non-default values

### UX flow

```
Detected variables in .env.example:

Secret-backed (recommended):
  [x] DB_DATABASE
  [x] DB_USERNAME
  [x] DB_PASSWORD
  [ ] MAIL_USERNAME
  [ ] MAIL_PASSWORD

Use multiselect() to let user pick which to include.

Static values:
  [x] SESSION_DRIVER = database
  [ ] LARAVEL_PDF_DRIVER = dompdf

Confirm selections? (yes/no) [yes]:
```

With Laravel Prompts `multiselect()`, users can arrow-key through the list and toggle items on/off instead of typing each name.

### Implementation

1. Read `.env.example` from project root (fallback: skip if not found)
2. Parse key-value pairs
3. Categorize known keys into secret-backed vs. static suggestions
4. Present with `multiselect()` for each category
5. Write selections to `netsons-deploy.json`

### Affected files

| File | Changes |
|------|---------|
| `src/Commands/InstallCommand.php` | Add `.env.example` parsing, replace manual entry loop with multiselect |
| `src/Services/DeployConfigManager.php` | No changes needed |

### Dependency

Depends on B1 (Laravel Prompts) for `multiselect()` — already implemented.

---

## B6: Envaudit integration as opt-in default

**Priority:** Enhancement
**Status:** DONE
**Date added:** 2026-05-10
**Date completed:** 2026-05-10

### What

Add [envaudit](https://www.npmjs.com/package/@albertoarena/envaudit) as an opt-in (default yes) validation step in the deploy workflow. Envaudit validates the `.env` file after deployment to catch missing or misconfigured variables before the release goes live.

### UX flow

During `netsons:install` interactive setup:

```
Enable envaudit .env validation after deploy?
  See: https://albertoarena.github.io/envaudit/getting-started/ci-integration/
  (yes/no) [yes]:
```

Defaulting to **yes** means envaudit is included unless the user explicitly skips it. The link lets users evaluate the tool before deciding.

Use the `hint` parameter in Laravel Prompts `confirm()`:

```php
confirm(
    label: 'Enable envaudit .env validation after deploy?',
    default: true,
    hint: 'See: https://albertoarena.github.io/envaudit/getting-started/ci-integration/',
);
```

### Workflow step

Added after "Update .env values" and before "Run migrations":

```yaml
- name: Validate .env with envaudit
  env:
    SSH_HOST: ${{ vars.SSH_HOST }}
    SSH_PORT: ${{ vars.SSH_PORT || '65100' }}
    SSH_USER: ${{ vars.SSH_USER }}
    DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
  run: |
    scp -P ${SSH_PORT} \
      ${SSH_USER}@${SSH_HOST}:~/${{ vars.DEPLOY_PATH }}/shared/.env .env
    npx @albertoarena/envaudit check --ci --no-color
    rm .env
```

This downloads the remote `.env`, validates it locally (Node is already installed in the runner), and fails the deploy if critical variables are missing.

### Configuration

**`netsons-deploy.json`:**
```json
{
    "envaudit": true
}
```

**`stubs/workflows/deploy.yml.stub`:**
- Add `%%ENVAUDIT%%` placeholder after "Update .env values"
- `InstallCommand` replaces it with the validation step or empty string

### Implementation

1. Add `envaudit` key to `DeployConfigManager` defaults
2. Add prompt to `InstallCommand::collectDeployJson()` — default yes
3. Add `%%ENVAUDIT%%` placeholder to workflow stub
4. Add generation logic in `InstallCommand::publishWorkflow()`
5. Add `netsons:env` support for toggling envaudit on/off
6. Update docs (configuration.md, website)

### Affected files

| File | Changes |
|------|---------|
| `src/Services/DeployConfigManager.php` | Add `envaudit` to defaults |
| `src/Commands/InstallCommand.php` | Add envaudit prompt + workflow generation |
| `src/Commands/EnvCommand.php` | Show envaudit status in list |
| `stubs/workflows/deploy.yml.stub` | Add `%%ENVAUDIT%%` placeholder |
| `docs/configuration.md` | Document envaudit config |
| `website/src/content/docs/reference/configuration.mdx` | Mirror docs |
