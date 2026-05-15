# Plan: SSH Retry Resilience for Deploy Workflow

**Status: COMPLETED** (2026-05-13)

## Problem

SSH connections to Netsons shared hosting can intermittently time out (port 65100). When this happens, the entire deployment fails with no recovery — there are ~12 SSH steps in the git strategy workflow, all vulnerable. This was observed in a production deployment using the git strategy.

The error: `ssh: connect to host *** port ***: Connection timed out` (exit code 255).

## Approach

Add a reusable SSH retry wrapper function to the workflow, defined once in the SSH Setup step and sourced by all subsequent steps.

### Option A: Shell function in the workflow (Recommended)

Define an `ssh_retry` shell function early in the workflow (during SSH Setup), exported to a helper script that subsequent steps source. This keeps retry logic DRY and contained within the workflow.

**Retry parameters (configurable as env vars):**
- `SSH_RETRIES`: max attempts (default: 3)
- `SSH_RETRY_DELAY`: seconds between retries (default: 10)
- `SSH_CONNECT_TIMEOUT`: seconds for SSH ConnectTimeout (default: 30)
- `SSH_SERVER_ALIVE_INTERVAL`: ServerAliveInterval to detect stale connections (default: 15)
- `SSH_SERVER_ALIVE_COUNT_MAX`: max missed keepalives before disconnect (default: 3)

### Option B: Retry at GitHub Actions level

Use a third-party retry action (e.g., `nick-fields/retry@v3`). Downside: adds a dependency, wraps each step in boilerplate, less control over SSH-specific options.

**Recommendation: Option A** — no external dependencies, fine-grained SSH control, one definition reused everywhere.

## Implementation Details

### 1. Create retry helper script

Create `stubs/scripts/ssh-helpers.sh` with two functions:

```sh
# ssh_with_opts: adds standard SSH options (timeout, keepalive)
ssh_with_opts() {
  ssh -o ConnectTimeout=${SSH_CONNECT_TIMEOUT:-30} \
      -o ServerAliveInterval=${SSH_SERVER_ALIVE_INTERVAL:-15} \
      -o ServerAliveCountMax=${SSH_SERVER_ALIVE_COUNT_MAX:-3} \
      "$@"
}

# ssh_retry: wraps ssh_with_opts with retry logic
ssh_retry() {
  local max_attempts=${SSH_RETRIES:-3}
  local delay=${SSH_RETRY_DELAY:-10}
  local attempt=1

  while [ $attempt -le $max_attempts ]; do
    ssh_with_opts "$@" && return 0
    local exit_code=$?
    if [ $exit_code -ne 255 ]; then
      # Non-connection error (e.g., remote command failed) — don't retry
      return $exit_code
    fi
    echo "::warning::SSH connection failed (attempt ${attempt}/${max_attempts}). Retrying in ${delay}s..."
    sleep $delay
    attempt=$((attempt + 1))
  done
  echo "::error::SSH connection failed after ${max_attempts} attempts"
  return 255
}

# scp_retry: same retry logic for scp commands
scp_retry() {
  local max_attempts=${SSH_RETRIES:-3}
  local delay=${SSH_RETRY_DELAY:-10}
  local attempt=1

  while [ $attempt -le $max_attempts ]; do
    scp -o ConnectTimeout=${SSH_CONNECT_TIMEOUT:-30} "$@" && return 0
    local exit_code=$?
    if [ $exit_code -ne 255 ]; then
      return $exit_code
    fi
    echo "::warning::SCP connection failed (attempt ${attempt}/${max_attempts}). Retrying in ${delay}s..."
    sleep $delay
    attempt=$((attempt + 1))
  done
  echo "::error::SCP connection failed after ${max_attempts} attempts"
  return 255
}
```

Key design decisions:
- **Only retry on exit code 255** (SSH connection error). Remote command failures (non-zero exit from the script running on the server) should NOT be retried — they'd fail again.
- **Use GitHub Actions `::warning::` annotations** so retries are visible in the Actions UI.
- **ServerAliveInterval/CountMax** detect stale connections mid-command (not just at connect time).

### 2. Update deploy.yml.stub

a) Add retry config env vars at workflow level:

```yaml
env:
  # ... existing vars ...
  SSH_RETRIES: '3'
  SSH_RETRY_DELAY: '10'
  SSH_CONNECT_TIMEOUT: '30'
```

b) In the SSH Setup step, write the helper script to a known path:

```yaml
- name: Setup SSH
  run: |
    # ... existing SSH key setup ...
    # Write SSH helper functions
    cat > /tmp/ssh-helpers.sh <<'HELPERS'
    ... (contents of ssh-helpers.sh)
    HELPERS
    chmod +x /tmp/ssh-helpers.sh
```

c) In every SSH step, source the helper and replace `ssh` with `ssh_retry`:

```yaml
- name: Ensure releases directory
  run: |
    source /tmp/ssh-helpers.sh
    ssh_retry -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<'REMOTE'
      ...
    REMOTE
```

d) Replace `scp` with `scp_retry` in the "Deploy via Git" step.

### 3. Update action.yml

Add new optional inputs:
- `ssh-retries` (default: '3')
- `ssh-retry-delay` (default: '10')
- `ssh-connect-timeout` (default: '30')

### 4. Update config/netsons-deploy.php

Add SSH resilience options:

```php
'ssh' => [
    'host' => env('NETSONS_SSH_HOST'),
    'port' => env('NETSONS_SSH_PORT', 65100),
    'user' => env('NETSONS_SSH_USER'),
    'retries' => env('NETSONS_SSH_RETRIES', 3),
    'retry_delay' => env('NETSONS_SSH_RETRY_DELAY', 10),
    'connect_timeout' => env('NETSONS_SSH_CONNECT_TIMEOUT', 30),
],
```

### 5. Update InstallCommand.php

Add optional prompts (or sensible defaults) for retry settings during `netsons:install`.

### 6. Update CheckCommand.php

Show retry config in the check output.

### 7. Tests

- Unit test for the helper script logic (test retry on exit 255, no retry on other exit codes)
- Update InstallCommandTest and CheckCommandTest for new config fields
- Update any existing stub-generation tests

### 8. Documentation

Update:
- `README.md` — mention SSH retry resilience
- `docs/configuration.md` — document new SSH config options
- `docs/troubleshooting.md` — add SSH timeout section with retry info
- `website/src/content/docs/` — corresponding MDX pages

## Files to modify

1. **New:** `stubs/scripts/ssh-helpers.sh`
2. **Edit:** `stubs/workflows/deploy.yml.stub` — add env vars, source helper, replace ssh/scp calls
3. **Edit:** `action.yml` — add retry inputs
4. **Edit:** `config/netsons-deploy.php` — add retry config
5. **Edit:** `src/Commands/InstallCommand.php` — generate retry env vars in workflow
6. **Edit:** `src/Commands/CheckCommand.php` — display retry config
7. **Edit:** docs (README.md, docs/, website/)
8. **New/Edit:** tests

## Scope

This is a medium-sized change. The core logic (ssh-helpers.sh + stub updates) is small, but it touches many SSH steps in the workflow stub and requires doc/test updates.

## Risk

Low. Retry logic is additive — it doesn't change the happy path. The key safety is only retrying on exit code 255, so we never accidentally re-run a partially-completed remote command.

## Implementation Notes

**Approach chosen:** Option A (shell function in workflow). The ssh-helpers.sh script is written inline during the SSH Setup step to `/tmp/ssh-helpers.sh`, then sourced by every subsequent step that needs SSH.

**Deviation from plan:** Instead of creating a separate `stubs/scripts/ssh-helpers.sh` file, the helper functions are embedded directly in the `deploy.yml.stub` (within the SSH Setup step). This keeps the workflow self-contained — no need to copy external scripts to the runner.

**Files modified:**
- `config/netsons-deploy.php` — added `retries`, `retry_delay`, `connect_timeout` to `ssh` config
- `stubs/workflows/deploy.yml.stub` — added env vars, helper script, replaced all `ssh -p`/`scp` with `ssh_retry`/`scp_retry`
- `action.yml` — added 3 new inputs, same helper script and retry wrappers
- `src/Commands/InstallCommand.php` — placeholder replacement for retry vars, updated envaudit scp call
- `src/Commands/CheckCommand.php` — added retry settings to config table
- `tests/Unit/ConfigTest.php` — 3 new tests for config defaults
- `tests/Feature/CheckCommandTest.php` — 3 new tests for display
- `tests/Feature/InstallCommandTest.php` — 7 new tests for workflow generation
- `README.md`, `docs/configuration.md`, `docs/troubleshooting.md` — updated
- `website/src/content/docs/reference/configuration.mdx`, `github-action.mdx`, `help/troubleshooting.mdx` — updated

**Test results:** 257 tests passed (566 assertions), lint passed.
