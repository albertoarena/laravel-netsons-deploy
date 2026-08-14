# Plan: Git Strategy Workflow Optimization

**Origin:** Real-world testing of git strategy deployment revealed that the generated workflow runs unnecessary steps. Several build steps that only make sense for FTP are also executed for git, wasting CI minutes and adding confusion.

**Date:** 2026-05-12

---

## Problem

The current workflow stub treats both strategies almost identically in the build phase. This is wrong — the two strategies have fundamentally different flows:

### FTP Strategy Flow (correct)

1. **Runner** checks out code
2. **Runner** installs Composer dependencies (needed — the built app is uploaded)
3. **Runner** installs Node dependencies + builds assets (needed — built app is uploaded)
4. **Runner** uploads everything to server via FTP (incremental sync)
5. **Server** runs post-deploy (migrations, caches, symlinks)

Everything runs on the runner. The server receives a complete, pre-built application.

### Git Strategy Flow (current — broken logic)

1. **Runner** checks out code
2. **Runner** prepares Laravel directories (**unnecessary** — server clones fresh)
3. **Runner** installs Composer dependencies (**unnecessary** — server runs its own `composer install`)
4. **Runner** installs Node dependencies + builds assets (**necessary** — Node/Yarn not available on Netsons)
5. **Runner** SSHs to server, creates release dir, copies current release (**unnecessary** — git clone replaces everything)
6. **Server** clones repo, runs `composer install`, removes .git
7. **Runner** uploads `public/build` to server via SCP (**necessary** — built assets from step 4)
8. **Server** runs post-deploy (migrations, caches, symlinks)

### What's wrong

| Step | FTP needs it? | Git needs it? | Currently runs for git? |
|---|---|---|---|
| Prepare Laravel directories | Yes | No | Yes (wasted) |
| Setup PHP (runner) | Yes | No | Yes (wasted) |
| Composer cache (runner) | Yes | No | Yes (wasted) |
| Composer install (runner) | Yes | No | Yes (wasted) |
| Setup Node | Yes | Yes | Yes |
| Node install + build | Yes | Yes | Yes |
| Create release dir + copy current | Yes (FTP diffs against it) | No (git clone replaces it) | Yes (then immediately `rm -rf`'d) |

The runner wastes ~30-60 seconds on PHP setup + Composer install that the git strategy never uses.

---

## Proposed Changes

### G1: Skip PHP/Composer on runner for git strategy

Add `if: env.STRATEGY == 'ftp'` to these steps:

- "Prepare Laravel directories"
- "Setup PHP"
- "Get Composer cache directory"
- "Cache Composer dependencies"
- "Install Composer dependencies"

These are only needed for FTP (which uploads the built app). For git, the server handles its own `composer install`.

**Note:** Node setup + build must still run for both strategies — Netsons doesn't have Node, so assets are always built on the runner and uploaded via SCP.

### G2: Skip "Create release directory + copy current" for git strategy

Add `if: env.STRATEGY == 'ftp'` to the "Create release directory" step.

For git, the deploy step already handles directory creation via `git clone`. The current flow creates the dir, copies the previous release into it, then the git step immediately does `rm -rf` and clones fresh — completely pointless.

For git, add a simpler "Create releases dir" step that just ensures the `releases/` parent directory exists (the git clone step needs it):

```yaml
- name: Ensure releases directory exists
  if: env.STRATEGY == 'git'
  run: |
    ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<'REMOTE'
      set -euo pipefail
      mkdir -p ~/${{ vars.DEPLOY_PATH }}/releases
    REMOTE
```

### G3: Update action.yml with same conditionals

Mirror G1 and G2 changes in `action.yml`. Since `action.yml` is a composite action (not a workflow stub), it doesn't do PHP/Composer/Node setup — the caller does that. But the "Create release directory" step has the same copy-then-delete issue and should be conditional.

### G4: Update deploy-git.sh script

The standalone script already only runs git-specific logic, so no changes needed.

### G5: Update documentation

Update docs to clearly explain the difference:

- **FTP**: everything built on runner, uploaded to server
- **Git**: code cloned on server, Composer runs on server, only Node-built assets uploaded from runner

Files:
- `docs/git-strategy.md`
- `docs/ftp-strategy.md`
- `website/src/content/docs/strategies/git.mdx`
- `website/src/content/docs/strategies/ftp.mdx`
- `README.md` (strategy comparison table)

### G6: Tests

Update existing tests + add new ones:
- Verify git strategy workflow does NOT contain `composer install --no-dev` as a runner step (it should only appear inside the SSH remote block)
- Verify git strategy workflow has `if: env.STRATEGY == 'ftp'` on PHP/Composer steps
- Verify FTP strategy workflow still has all build steps without conditionals
- Verify git strategy does not have the "copy current release" logic

---

## Summary Table

| # | Item | Files | Type |
|---|------|-------|------|
| G1 | Skip PHP/Composer on runner for git | `deploy.yml.stub` | Fix |
| G2 | Skip "copy current release" for git | `deploy.yml.stub` | Fix |
| G3 | Same conditionals in action.yml | `action.yml` | Fix |
| G4 | deploy-git.sh (no changes needed) | — | — |
| G5 | Documentation | docs, website, README | Docs |
| G6 | Tests | `tests/Feature/InstallCommandTest.php` | Tests |

## Execution Order (TDD)

1. **G6** — Write failing tests
2. **G1 + G2** — Update `deploy.yml.stub`
3. **G3** — Update `action.yml`
4. **G6** — Verify tests pass
5. **G5** — Update docs

## What This Looks Like After

### Git strategy workflow (optimized)

```
Checkout code
  ↓
Setup Node → Install Node deps → Build assets (yarn/npm)
  ↓
Setup SSH
  ↓
SSH: ensure releases/ dir exists
  ↓
SSH (-A): ssh-keyscan github.com → git clone → composer install → cleanup
  ↓
SCP: upload public/build to server
  ↓
SSH: shared resources (symlinks, .env, storage)
  ↓
SSH: post-deploy (migrations, caches, key:generate, seeders)
  ↓
SSH: activate release (symlink + proxy)
  ↓
SSH: cleanup old releases
  ↓
SSH cleanup (remove key, kill agent)
```

### FTP strategy workflow (unchanged)

```
Checkout code
  ↓
Prepare Laravel dirs
  ↓
Setup PHP → Composer install
  ↓
Setup Node → Install Node deps → Build assets
  ↓
Setup SSH
  ↓
SSH: create release dir + copy current
  ↓
FTP: upload everything (incremental sync)
  ↓
SSH: shared resources (symlinks, .env, storage)
  ↓
SSH: post-deploy (migrations, caches, key:generate, seeders)
  ↓
SSH: activate release (symlink + proxy)
  ↓
SSH: cleanup old releases
  ↓
SSH cleanup (remove key, kill agent)
```
