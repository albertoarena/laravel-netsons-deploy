# Plan: Switch Git Strategy to HTTPS Cloning

**Origin:** Real-world testing revealed that Netsons shared hosting blocks outbound SSH connections (port 22) from the server. This means `git clone git@github.com:...` fails, and SSH agent forwarding (`-A`) is useless — the server simply cannot reach GitHub via SSH.

**Date:** 2026-05-13

---

## Problem

The git strategy runs `git clone` on the Netsons server via an SSH session. The clone URL uses SSH format (`git@github.com:user/repo.git`), which requires the server to make an outbound SSH connection to GitHub on port 22.

Netsons shared hosting blocks outbound SSH. The `ssh-keyscan github.com` command also fails for the same reason. This was confirmed by debug output showing:

```
+ ssh-keyscan github.com
debug1: Exit status 1
```

**The entire SSH agent forwarding approach (v1.7.0) is invalid for Netsons.**

## Solution: HTTPS Cloning

Use HTTPS URLs instead of SSH for `git clone`. HTTPS uses port 443, which is not blocked on shared hosting.

- **Public repos:** `https://github.com/user/repo.git` — no auth needed
- **Private repos:** `https://x-access-token:TOKEN@github.com/user/repo.git` — needs a token

### Token for private repos

GitHub Actions provides `${{ github.token }}` (aka `GITHUB_TOKEN`) automatically. It has read access to the repository that triggered the workflow. This token:

- Is auto-generated per workflow run — no manual setup
- Has read access to the triggering repo by default
- Expires after the job completes
- Does NOT require the user to create a PAT

For cross-repo cloning (deploying repo A from repo B's workflow), a PAT or fine-grained token would be needed, but this is an edge case.

## Changes

### Summary

| # | Item | Files | Type |
|---|------|-------|------|
| H1 | Revert SSH agent forwarding (`-A` and `ssh-keyscan`) | `action.yml`, `deploy.yml.stub`, `deploy-git.sh` | Fix |
| H2 | Switch to HTTPS clone with optional token | `action.yml`, `deploy.yml.stub`, `deploy-git.sh` | Fix |
| H3 | Add `git-token` input to action.yml | `action.yml` | Enhancement |
| H4 | Update workflow stub to pass token | `deploy.yml.stub` | Enhancement |
| H5 | Update InstallCommand GIT_REPO description | `InstallCommand.php` | Fix |
| H6 | Rewrite all SSH-agent-forwarding docs | docs, website, README | Docs |
| H7 | Update troubleshooting | docs, website | Docs |
| H8 | Tests | `tests/Feature/InstallCommandTest.php` | Tests |

---

### H1: Revert SSH agent forwarding

Remove from all three files:

- `ssh -A` flag → back to `ssh`
- `ssh-keyscan github.com` block → remove entirely
- `sort -u` known_hosts → remove entirely

**action.yml** (line ~174):
```yaml
# Before
ssh -A -p ${{ inputs.ssh-port }} ...

# After
ssh -p ${{ inputs.ssh-port }} ...
```

**stubs/workflows/deploy.yml.stub** (line ~202):
```yaml
# Before
ssh -A -p ${SSH_PORT} ...

# After
ssh -p ${SSH_PORT} ...
```

**stubs/scripts/deploy-git.sh** (line ~29):
```bash
# Before
SSH_CMD="ssh -A -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

# After
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
```

Remove the entire `ssh-keyscan` + `sort -u` block from all three files.

---

### H2: Switch to HTTPS clone with optional token

The clone URL logic:

```bash
# If a token is provided, inject it into the HTTPS URL for private repo access
if [ -n "${GIT_TOKEN}" ]; then
  CLONE_URL=$(echo "${GIT_REPO}" | sed "s|https://github.com/|https://x-access-token:${GIT_TOKEN}@github.com/|")
else
  CLONE_URL="${GIT_REPO}"
fi

git clone --branch ${GIT_BRANCH} --single-branch --depth 1 ${CLONE_URL} releases/${RELEASE_DIR}
```

This handles both scenarios:
- **Public repo:** `GIT_TOKEN` is empty → clones with plain HTTPS URL
- **Private repo:** `GIT_TOKEN` is set → injects token into URL

The token is NOT persisted on the server — it's only used for the clone command and the `.git` directory is deleted immediately after (`rm -rf .git`).

---

### H3: Add `git-token` input to action.yml

```yaml
inputs:
  git-token:
    description: 'GitHub token for private repo access (git strategy only). Use ${{ github.token }} for same-repo deploys.'
    default: ''
```

Pass it through to the deploy step:

```yaml
- name: Deploy via Git
  if: inputs.strategy == 'git'
  shell: bash
  env:
    GIT_TOKEN: ${{ inputs.git-token }}
  run: |
    ssh -p ${{ inputs.ssh-port }} ...
```

---

### H4: Update workflow stub to pass token

In `deploy.yml.stub`, the "Deploy via Git" step env block:

```yaml
env:
  SSH_HOST: ${{ secrets.SSH_HOST }}
  SSH_PORT: ${{ secrets.SSH_PORT || '65100' }}
  SSH_USER: ${{ secrets.SSH_USER }}
  DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
  GIT_REPO: ${{ vars.GIT_REPO }}
  GIT_BRANCH: ${{ vars.GIT_BRANCH || 'main' }}
  GIT_TOKEN: ${{ secrets.GIT_TOKEN }}
  REMOTE_PHP: ${{ env.REMOTE_PHP }}
```

Where `GIT_TOKEN` is a new optional secret. For same-repo deploys, users can set it to `${{ github.token }}` directly in the workflow env block (or leave the secret empty for public repos).

**Important:** The `GIT_TOKEN` must be passed as an env var to the SSH session, then used inside the heredoc. It must NOT appear in the clone URL in the workflow file itself (it would be visible in logs).

In the remote script, mask the token:

```bash
# Clone with token injection (token is not persisted — .git is deleted after)
if [ -n "${GIT_TOKEN}" ]; then
  CLONE_URL=$(echo "${GIT_REPO}" | sed "s|https://github.com/|https://x-access-token:${GIT_TOKEN}@github.com/|")
else
  CLONE_URL="${GIT_REPO}"
fi
git clone --branch ${GIT_BRANCH} --single-branch --depth 1 ${CLONE_URL} releases/${RELEASE_DIR}
```

Since the heredoc uses `<<REMOTE` (unquoted), env vars are expanded locally on the runner. The token never appears in the command as printed by GitHub Actions because `secrets.*` values are masked.

---

### H5: Update InstallCommand GIT_REPO description

```php
// Before
'GIT_REPO' => 'Git repository URL (e.g. git@github.com:user/repo.git)',

// After
'GIT_REPO' => 'Git repository URL (e.g. https://github.com/user/repo.git)',
```

Also update `config/netsons-deploy.php` comments and `CLAUDE.md` if they reference SSH URL format.

---

### H6: Rewrite docs for HTTPS cloning

All references to SSH agent forwarding, deploy keys, and `git@github.com:` format must be replaced.

**docs/git-strategy.md** — Rewrite "Private Repositories" section:

```markdown
## Public Repositories

For public repos, set `GIT_REPO` to the HTTPS URL:

```
https://github.com/user/repo.git
```

No additional configuration is needed.

## Private Repositories

For private repos, you need a GitHub token so the Netsons server can
authenticate when cloning.

### Using GITHUB_TOKEN (recommended for same-repo deploys)

The simplest approach: edit your `.github/workflows/deploy.yml` and
change the `GIT_TOKEN` env var in the "Deploy via Git" step:

```yaml
GIT_TOKEN: ${{ github.token }}
```

`github.token` is automatically provided by GitHub Actions with read
access to the repository. No secrets to create or rotate.

### Using a Personal Access Token (cross-repo or fine-grained control)

1. Create a fine-grained PAT at github.com > Settings > Developer settings >
   Personal access tokens > Fine-grained tokens
2. Grant **read-only** access to the repository contents
3. Add it as a secret named `GIT_TOKEN` in your repo (Settings > Secrets)
4. The workflow already references `${{ secrets.GIT_TOKEN }}`
```

**docs/github-secrets.md** — Replace "Private Repository Setup" section with token-based approach.

**docs/netsons-setup.md** — Remove deploy key reference, add note about HTTPS.

**docs/troubleshooting.md** — Replace agent forwarding troubleshooting with HTTPS troubleshooting.

**README.md** — Update strategy comparison table.

**website/** — Mirror all doc changes in corresponding MDX files.

---

### H7: Update troubleshooting

Replace the "Git clone fails with Permission denied" entry:

```markdown
### Git clone fails on the server

Netsons shared hosting blocks outbound SSH (port 22), so
`git@github.com:...` URLs do not work. Use HTTPS format:

```
GIT_REPO: https://github.com/user/repo.git
```

If the repo is private, ensure `GIT_TOKEN` is configured.
See [Private Repositories](git-strategy.md#private-repositories).

### "fatal: could not read Username" during git clone

This means the repo is private but no token was provided.
Set `GIT_TOKEN` — see [Private Repositories](git-strategy.md#private-repositories).
```

---

### H8: Tests

**Remove/replace:**
- Test "uses SSH agent forwarding in git deploy step" → replace with test that git deploy step does NOT contain `ssh -A`
- Test "does not use SSH agent forwarding in non-git SSH steps" → remove
- Test "injects GitHub known hosts in git deploy step" → replace with test that workflow does NOT contain `ssh-keyscan github.com`

**Add:**
- Test that git deploy step contains `GIT_TOKEN` env var
- Test that git deploy step contains token injection logic (`x-access-token`)
- Test that `GIT_REPO` description in command output shows HTTPS format

---

## GIT_REPO Format Change

| Before (v1.7.0) | After |
|---|---|
| `git@github.com:user/repo.git` | `https://github.com/user/repo.git` |

This is a **breaking change** for existing git strategy users. Document in release notes that `GIT_REPO` must be updated to HTTPS format.

---

## What Gets Reverted from v1.7.0

| v1.7.0 addition | Action |
|---|---|
| `ssh -A` flag | Reverted — not needed with HTTPS |
| `ssh-keyscan github.com` | Reverted — not needed with HTTPS |
| Deploy key documentation | Removed — tokens replace deploy keys |
| GitHub known hosts injection | Removed — HTTPS doesn't use SSH |

---

## Execution Order (TDD)

1. **H8** — Write failing tests
2. **H1** — Revert SSH agent forwarding
3. **H2 + H3 + H4** — Implement HTTPS cloning with token
4. **H5** — Update InstallCommand description
5. **H8** — Verify tests pass
6. **H6 + H7** — Update all docs and website

## Security Notes

- The `GIT_TOKEN` is passed as an env var through the SSH session. Since the heredoc is unquoted (`<<REMOTE`), the token is expanded on the runner and sent to the server as a literal string inside the bash script.
- GitHub Actions masks `secrets.*` values in logs, so the token won't appear in workflow output.
- The `.git` directory (which contains the token in the remote URL) is deleted immediately after clone (`rm -rf .git`).
- The token is short-lived (expires after the workflow job completes for `github.token`).

## Risks

| Risk | Mitigation |
|---|---|
| Breaking change: GIT_REPO format | Document clearly in release notes, show migration path |
| Token visible in server process list during clone | Short-lived, `.git` deleted immediately, server is user's own cPanel account |
| `github.token` doesn't work for cross-repo | Document PAT alternative for that use case |
| HTTPS may be slower than SSH for clone | Negligible for `--depth 1` shallow clones |
