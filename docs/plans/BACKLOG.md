# Backlog

---

## B1: Adopt Laravel Prompts for interactive commands

**Priority:** Enhancement
**Status:** Planned
**Date added:** 2026-05-10

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
**Status:** Planned
**Date added:** 2026-05-10

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
