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
