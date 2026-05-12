# Plan: Fix clone URL passing via SSH heredoc

**Origin:** Git strategy deployment fails with `fatal: could not read Username for 'https://github.com'` because the clone URL (with injected token) is not reaching the remote bash correctly.

**Date:** 2026-05-13

---

## Problem

The clone URL (with token injected) was passed as a positional argument to the remote bash via:

```bash
ssh ... bash -s -- "${CLONE_URL}" <<'REMOTE'
  CLONE_URL="$1"
  git clone ... "${CLONE_URL}" ...
REMOTE
```

This doesn't work reliably because:
1. `bash -s -- args` combined with a heredoc through SSH doesn't forward `$1` correctly on all SSH/bash versions
2. The quoted heredoc `<<'REMOTE'` prevents local variable expansion, so `$1` is the only way to get the URL in — and it fails

## Fix

Use an **unquoted heredoc** (`<<REMOTE`) so the `CLONE_URL` variable is expanded locally on the runner before being sent to the server:

```bash
ssh ... bash -s <<REMOTE
  set -euo pipefail
  git clone ... "${CLONE_URL}" ...
REMOTE
```

This is safe because:
- `CLONE_URL` is constructed on the runner and expanded into the heredoc before SSH sends it
- GitHub Actions masks `secrets.*` values in logs, so the token won't appear in output
- The `.git` directory (which contains the URL with token) is deleted immediately after clone
- Other `${{ }}` expressions are GitHub Actions expressions, expanded before bash runs

## Changes

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Remove `bash -s --`, use unquoted `<<REMOTE`, remove `CLONE_URL="$1"` |
| `action.yml` | Same |
| `stubs/scripts/deploy-git.sh` | Same |

## Tests

All 239 existing tests pass — no test changes needed since tests check for `x-access-token` and `GIT_TOKEN` presence, not the heredoc quoting style.
