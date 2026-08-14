# Plan: SSH Agent Forwarding for Private Repos

**Origin:** Git strategy uses `git clone` on the Netsons server, but the SSH key is only loaded in the GitHub Actions runner's agent. Cloning a private repo fails because the server has no credentials to authenticate with GitHub.

**Date:** 2026-05-12

---

## Problem

When deploying with the git strategy, the workflow:

1. Loads `SSH_PRIVATE_KEY` into the **GitHub Actions runner's** SSH agent
2. Uses that key to SSH into the Netsons server
3. On the Netsons server, runs `git clone git@github.com:user/repo.git`

Step 3 fails for private repos because the Netsons server has no GitHub credentials. The SSH key exists only on the runner — it is never forwarded to the remote session.

This affects:
- `action.yml` line 167: `git clone` inside an SSH session
- `stubs/workflows/deploy.yml.stub` line 186: same pattern
- `stubs/scripts/deploy-git.sh` line 43: same pattern

## Approach: SSH Agent Forwarding (`-A`)

Add the `-A` flag to the SSH command that runs `git clone`. This forwards the runner's SSH agent to the Netsons server, so `git clone git@github.com:...` authenticates through the forwarded agent.

### Why `-A` and not alternatives

| Option | Pros | Cons |
|---|---|---|
| **`-A` agent forwarding** | No extra setup, uses existing key, works with passphrase-protected keys | Requires `AllowAgentForwarding yes` on server (default on most) |
| **Deploy key on Netsons** | Independent of runner | Manual setup, extra key to manage, must be documented as user responsibility |
| **HTTPS + `GITHUB_TOKEN`** | No SSH needed for git | Token has broad repo access, expires, doesn't work for cross-org repos |
| **HTTPS + PAT** | Works everywhere | User must create and rotate PAT, security risk if leaked |

Agent forwarding is the best fit because:
- The SSH key is already loaded in the runner's agent
- No additional secrets or setup required from the user
- Works transparently for both public and private repos
- The same key that authenticates to Netsons can also authenticate to GitHub (user adds the public key as a GitHub deploy key)

### Security note

Agent forwarding exposes the runner's SSH agent to the remote server for the duration of the session. On shared hosting this is acceptable because:
- The session is short-lived (single `git clone` + `composer install`)
- The remote user is the deployer's own cPanel account
- GitHub Actions runners are ephemeral — the agent is destroyed after the job

## Changes

### Summary

| # | Item | Files | Type |
|---|------|-------|------|
| S1 | Add `-A` to git clone SSH command | `action.yml`, `deploy.yml.stub`, `deploy-git.sh` | Fix |
| S2 | Add GitHub known hosts on Netsons | `action.yml`, `deploy.yml.stub`, `deploy-git.sh` | Fix |
| S3 | Document deploy key setup for private repos | `docs/github-secrets.md`, `docs/git-strategy.md`, `docs/netsons-setup.md` | Docs |
| S4 | Update website docs | `website/src/content/docs/` | Docs |
| S5 | Add tests | `tests/` | Tests |

---

### S1: Add `-A` flag to SSH command for git clone step

Agent forwarding is only needed for the `git clone` step — all other SSH commands (create release dir, shared resources, post-deploy, etc.) don't need it and should **not** use `-A` to minimize exposure.

**action.yml** (line ~163 — "Deploy via Git" step):

```yaml
# Before
ssh -p ${{ inputs.ssh-port }} ${{ inputs.ssh-user }}@${{ inputs.ssh-host }} bash -s <<REMOTE

# After
ssh -A -p ${{ inputs.ssh-port }} ${{ inputs.ssh-user }}@${{ inputs.ssh-host }} bash -s <<REMOTE
```

**stubs/workflows/deploy.yml.stub** (line ~182 — "Deploy via Git" step):

```yaml
# Before
ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<REMOTE

# After
ssh -A -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<REMOTE
```

**stubs/scripts/deploy-git.sh** (line 29 and 34):

```bash
# Before
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

# After
SSH_CMD="ssh -A -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
```

---

### S2: Add GitHub known hosts on the Netsons server

When agent forwarding is active and the server runs `git clone git@github.com:...`, the server's SSH client needs GitHub's host key in its `known_hosts`. Without it, the clone hangs or fails with a host verification error.

The fix: before git clone, inject GitHub's known hosts on the remote server inside the same SSH session.

**action.yml** — inside the "Deploy via Git" remote script:

```bash
ssh -A -p ${{ inputs.ssh-port }} ${{ inputs.ssh-user }}@${{ inputs.ssh-host }} bash -s <<REMOTE
  set -euo pipefail
  # Ensure GitHub host keys are known on the server
  mkdir -p ~/.ssh
  ssh-keyscan github.com >> ~/.ssh/known_hosts 2>/dev/null
  sort -u -o ~/.ssh/known_hosts ~/.ssh/known_hosts

  cd ~/${{ inputs.deploy-path }}
  rm -rf releases/${{ steps.release.outputs.dir }}
  git clone --branch ${{ inputs.git-branch }} --single-branch --depth 1 ${{ inputs.git-repo }} releases/${{ steps.release.outputs.dir }}
  cd releases/${{ steps.release.outputs.dir }}
  ${{ inputs.remote-php }} /opt/cpanel/composer/bin/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
  rm -rf .git .github tests
REMOTE
```

Same pattern applies to `deploy.yml.stub` and `deploy-git.sh`.

**Note:** `ssh-keyscan github.com` is safe to run every deploy — `sort -u` deduplicates, and the output is deterministic. This avoids requiring users to manually add GitHub's host key to their Netsons server.

---

### S3: Document deploy key setup for private repos

**docs/github-secrets.md** — Add a new section after "Getting SSH Values":

```markdown
## Private Repository Setup (Git Strategy)

If your repository is private, the SSH key used for deployment must also have
read access to the GitHub repository. Two options:

### Option A: GitHub Deploy Key (Recommended)

1. Use the same key pair you generated for Netsons SSH access
2. Go to your GitHub repo > **Settings** > **Deploy keys**
3. Click **Add deploy key**
4. Paste the **public key** (`~/.ssh/id_ed25519.pub`)
5. Leave "Allow write access" unchecked (read-only is sufficient)
6. Click **Add key**

The deployment workflow uses SSH agent forwarding to pass this key
through to the Netsons server when cloning.

### Option B: Separate Deploy Key

If you prefer not to reuse the Netsons SSH key:

1. Generate a new key pair: `ssh-keygen -t ed25519 -C "deploy-github"`
2. Add the **public key** as a GitHub deploy key (see above)
3. Add the **private key** as the `SSH_PRIVATE_KEY` GitHub secret

> **Note:** The same private key must authenticate to both Netsons (SSH)
> and GitHub (git clone). If you use separate keys, you need a more
> complex SSH config — using one key for both is simpler.
```

**docs/git-strategy.md** — Add a note in the "How It Works" section:

```markdown
> **Private repos:** The workflow uses SSH agent forwarding (`-A`) so the
> runner's SSH key is available on the Netsons server during `git clone`.
> You must add the key's public half as a GitHub deploy key. See
> [GitHub Secrets](github-secrets.md#private-repository-setup-git-strategy).
```

**docs/netsons-setup.md** — Add under "Git (SSD 30+ Plans)":

```markdown
### Private Repositories

For private repos, the SSH key used for Netsons access must also be
registered as a deploy key on GitHub. See
[Private Repository Setup](github-secrets.md#private-repository-setup-git-strategy).
```

---

### S4: Update website docs

Mirror S3 changes in the corresponding MDX pages:

- `website/src/content/docs/reference/github-secrets.mdx`
- `website/src/content/docs/strategies/git.mdx`
- `website/src/content/docs/getting-started/netsons-setup.mdx`

---

### S5: Tests

**Unit tests** (new or updated):

- Verify `action.yml` "Deploy via Git" step contains `ssh -A`
- Verify generated workflow (`deploy.yml.stub` output) contains `ssh -A` in the git deploy step but **not** in other SSH steps
- Verify generated workflow contains `ssh-keyscan github.com` in the git deploy step
- Verify `deploy-git.sh` contains `ssh -A`

**Files:**

- `tests/Unit/ActionYmlTest.php` (new or existing)
- `tests/Feature/InstallCommandTest.php` (extend existing git strategy tests)

---

## Execution Order

1. **S5** — Write failing tests (TDD)
2. **S1** — Add `-A` flag to SSH commands
3. **S2** — Add GitHub known hosts injection
4. **S5** — Verify tests pass
5. **S3** — Update docs
6. **S4** — Update website docs

## Risks and Edge Cases

| Risk | Mitigation |
|---|---|
| `AllowAgentForwarding` disabled on Netsons | Unlikely (it's the default), but document as a troubleshooting step |
| User's SSH key is not a GitHub deploy key | Clear docs + error message from git clone will indicate permission denied |
| `ssh-keyscan github.com` blocked on Netsons | Extremely unlikely; fall back to hardcoding GitHub's known public keys |
| Public repos don't need any of this | No harm — `-A` and `ssh-keyscan` are no-ops for public repos |
| FTP strategy unaffected | Correct — no changes to FTP flow |
