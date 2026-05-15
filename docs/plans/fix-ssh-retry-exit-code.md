# Plan: Fix SSH/SCP Retry Exit Code Capture Bug

**Status: COMPLETED** (2026-05-15)

## Problem

The `ssh_retry` and `scp_retry` helper functions introduced in the SSH Retry Resilience plan (completed 2026-05-13) have a shell scripting bug that **prevents retries from ever happening**. On a real deployment failure (2026-05-15), Netsons timed out on two consecutive steps — neither showed any retry warning messages, confirming the retry logic never triggered.

### Root Cause

In both `ssh_retry` and `scp_retry`, the exit code is captured **after** an `if` statement, not directly from the command:

```bash
if ssh_with_opts "$@"; then
  return 0
fi
local exit_code=$?    # BUG: $? is the result of the `if` test (always 1), not ssh_with_opts
```

When `ssh_with_opts` fails with exit code 255 (SSH connection error), execution falls to the `fi` line. At that point, `$?` reflects the `if` statement's own result (1, meaning "condition was false"), **not** the 255 from `ssh_with_opts`. So the check `if [ $exit_code -ne 255 ]` is always true, and the function exits immediately on the first failure — no retries, ever.

The same bug exists in `scp_retry` with the `scp -o ConnectTimeout=...` command.

### Evidence from failed deployment

The failed run (attempt 1 only, no retries triggered):
- **"Update .env values"** step: `ssh_retry` called, SSH timed out after 30s, no `::warning::` retry messages in logs
- **"Validate .env with envaudit"** step: `scp_retry` called, SCP timed out, no `::warning::` retry messages in logs

Both steps should have retried 3 times with 10s delays. Instead they failed immediately.

## Fix

Replace the `if/fi` + `$?` pattern with direct exit code capture:

### Before (both functions)

```bash
while [ $attempt -le $max_attempts ]; do
  if ssh_with_opts "$@"; then
    return 0
  fi
  local exit_code=$?
  if [ $exit_code -ne 255 ]; then
    return $exit_code
  fi
```

### After

```bash
while [ $attempt -le $max_attempts ]; do
  ssh_with_opts "$@" && return 0
  local exit_code=$?
  if [ $exit_code -ne 255 ]; then
    return $exit_code
  fi
```

Using `command && return 0` instead of `if/fi` preserves `$?` from the actual command when it fails, because `&&` short-circuits on success but leaves `$?` set to the command's exit code on failure.

Same fix for `scp_retry`:

```bash
while [ $attempt -le $max_attempts ]; do
  scp -o ConnectTimeout=${SSH_CONNECT_TIMEOUT:-30} "$@" && return 0
  local exit_code=$?
  if [ $exit_code -ne 255 ]; then
    return $exit_code
  fi
```

## Files to Modify

The helper functions are defined in **three** places (all must be updated identically):

1. **`stubs/workflows/deploy.yml.stub`** — lines ~146-183 (the stub used by `netsons:install`)
2. **`action.yml`** — lines ~144-181 (the reusable GitHub Action)
3. **`docs/plans/ssh-retry-resilience.md`** — lines ~48-90 (code examples in the completed plan)

### Change summary per file

Each file has two functions to fix:

| Function | Old pattern | New pattern |
|---|---|---|
| `ssh_retry` | `if ssh_with_opts "$@"; then` / `return 0` / `fi` | `ssh_with_opts "$@" && return 0` |
| `scp_retry` | `if scp -o ConnectTimeout=... "$@"; then` / `return 0` / `fi` | `scp -o ConnectTimeout=... "$@" && return 0` |

No other files need changes — the `InstallCommand.php` generates the workflow from the stub, `CheckCommand.php` only displays config, and the test assertions check for `ssh_retry`/`scp_retry` presence (not the internal implementation).

## Tests

### New unit tests (Pest)

Add a shell-level test that verifies the retry behavior works correctly. Since these are shell functions, test via `Bash` execution in the test suite:

1. **`ssh_retry` retries on exit code 255** — mock `ssh_with_opts` to fail with 255 twice then succeed; verify 3 attempts were made
2. **`scp_retry` retries on exit code 255** — same pattern for SCP
3. **`ssh_retry` does NOT retry on non-255 exit codes** — mock failure with exit code 1; verify only 1 attempt
4. **`ssh_retry` fails after max attempts** — mock perpetual 255 failures; verify it gives up after 3 attempts and returns 255

### Existing tests

Review `tests/Feature/InstallCommandTest.php` lines 611-660 — these check that `ssh_retry`, `scp_retry`, and `ssh-helpers.sh` are present in generated workflows. They should still pass since we're not changing function names or the sourcing pattern.

## Documentation

### Update

- **`docs/plans/ssh-retry-resilience.md`** — update the code examples in the "Create retry helper script" section to use the fixed pattern. Add a note in the Implementation Notes section referencing this fix.

### No update needed

- `README.md`, `docs/configuration.md`, `docs/troubleshooting.md`, website MDX pages — the retry feature description and configuration docs are still accurate; this is an internal implementation fix, not a user-facing change.

## Scope

Small. Two lines changed in each of three files (6 lines total), plus a plan doc update and new tests.

## Risk

Very low. The fix is a minimal shell syntax change that:
- Preserves the exact same success path (`&& return 0` behaves identically to `if/then/return 0/fi` on success)
- Only changes behavior on failure, where it now correctly captures the exit code
- Does not change retry parameters, logging, or any other behavior
- The existing behavior is already broken (retries never fire), so the fix can only improve things
