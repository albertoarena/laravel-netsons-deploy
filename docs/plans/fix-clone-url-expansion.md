# Plan: Fix CLONE_URL not expanding in SSH heredoc

**Origin:** Git deploys fail with `ssh: Could not resolve hostname https` because `${CLONE_URL}` is not expanded in the unquoted heredoc. Git receives the literal string `${CLONE_URL}` and parses it as an SSH hostname.

**Date:** 2026-05-13

---

## Problem

The clone URL is constructed as a local shell variable, then referenced inside an unquoted heredoc:

```bash
CLONE_URL="https://x-access-token:TOKEN@github.com/user/repo.git"

ssh ... bash -s <<REMOTE
  git clone "${CLONE_URL}" releases/...
REMOTE
```

While bash normally expands `${CLONE_URL}` in unquoted heredocs, GitHub Actions may process the `run:` block in a way that prevents this expansion. The result is git receiving the literal string `${CLONE_URL}` instead of the actual URL.

Confirmed: HTTPS cloning works on the Netsons server when the URL is passed directly.

## Fix

Move the clone URL construction to the `run:` block and pass it to the server using `scp` of a temporary script file, then execute it remotely. 

Actually, the simplest approach: construct the clone command entirely on the runner as a string variable, then pass the full command to SSH via echo + pipe, avoiding heredoc variable expansion entirely.

**Simplest fix:** Use `echo "..." | ssh ... bash` instead of heredoc:

No — this has quoting issues too. 

**Correct fix:** Write a small temporary script file locally, scp it, execute remotely, delete it.

**Even simpler correct fix:** Just pass the URL as an SSH remote command argument directly, not inside a heredoc. Split the deploy step into two: one ssh call for clone (with URL inline), one for the rest.

**Actually the simplest correct fix:** Use `export CLONE_URL` so it becomes an environment variable in the shell, and pass it to the remote via `ssh -o SendEnv=CLONE_URL`. But `SendEnv` requires server-side `AcceptEnv` config which we don't control.

**Final approach (simplest that definitely works):** Construct the clone URL on the runner and write the entire SSH remote script to a temp file with the URL baked in, then pipe it to SSH:

```bash
# Build remote script with URL baked in (no heredoc expansion needed)
REMOTE_SCRIPT=$(cat <<SCRIPT
set -euo pipefail
cd ~/\${DEPLOY_PATH}
rm -rf releases/\${RELEASE_DIR}
git clone --branch main --single-branch --depth 1 "${CLONE_URL}" releases/\${RELEASE_DIR}
cd releases/\${RELEASE_DIR}
...
SCRIPT
)
echo "${REMOTE_SCRIPT}" | ssh ... bash -s
```

Wait, this has the same issue. The heredoc for `REMOTE_SCRIPT` would expand `${CLONE_URL}` fine since it's local, but then other vars that should be literal (`\${DEPLOY_PATH}`) need escaping.

**THE actual simplest fix:** Use the GitHub Actions `env:` block to compute the clone URL. Move the token injection logic to a prior step that sets a GitHub Actions env var via `$GITHUB_ENV`. Then the heredoc can use `${{ }}` expression syntax which is always expanded.

## Chosen Approach

Add a prior step "Prepare clone URL" that writes `CLONE_URL` to `$GITHUB_ENV`. Then the deploy step's heredoc doesn't need bash variable expansion — GitHub Actions replaces `${CLONE_URL}` as an env var before bash even sees it.

### Changes

**deploy.yml.stub:**

```yaml
      # ── Prepare clone URL (Git only) ──────────────────────────────────
      - name: Prepare clone URL
        if: env.STRATEGY == 'git'
        env:
          GIT_REPO: ${{ vars.GIT_REPO }}
          GIT_TOKEN: ${{ secrets.GIT_TOKEN }}
        run: |
          if [ -n "${GIT_TOKEN}" ]; then
            echo "CLONE_URL=$(echo "${GIT_REPO}" | sed "s|https://github.com/|https://x-access-token:${GIT_TOKEN}@github.com/|")" >> $GITHUB_ENV
          else
            echo "CLONE_URL=${GIT_REPO}" >> $GITHUB_ENV
          fi

      # ── Deploy (Git strategy) ──────────────────────────────────────────
      - name: Deploy via Git
        if: env.STRATEGY == 'git'
        env:
          SSH_HOST: ${{ secrets.SSH_HOST }}
          SSH_PORT: ${{ secrets.SSH_PORT || '65100' }}
          SSH_USER: ${{ secrets.SSH_USER }}
          DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
          REMOTE_PHP: ${{ env.REMOTE_PHP }}
        run: |
          ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} bash -s <<'REMOTE'
            set -euo pipefail
            cd ~/${{ vars.DEPLOY_PATH }}
            rm -rf releases/${{ steps.release.outputs.dir }}
            git clone --branch ${{ vars.GIT_BRANCH || 'main' }} --single-branch --depth 1 "${{ env.CLONE_URL }}" releases/${{ steps.release.outputs.dir }}
            cd releases/${{ steps.release.outputs.dir }}
            ${{ env.REMOTE_PHP }} /opt/cpanel/composer/bin/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
            rm -rf .git .github tests
          REMOTE
```

This works because:
- `CLONE_URL` is written to `$GITHUB_ENV` in a prior step
- It becomes available as `${{ env.CLONE_URL }}` in subsequent steps
- `${{ env.CLONE_URL }}` is a GitHub Actions expression, expanded before bash runs
- The heredoc can be quoted `<<'REMOTE'` again — no bash expansion needed
- GitHub Actions masks secrets in expressions, so the token won't appear in logs

### Files to change

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Add "Prepare clone URL" step, update "Deploy via Git" to use `${{ env.CLONE_URL }}` |
| `action.yml` | Same pattern |
| `stubs/scripts/deploy-git.sh` | Keep unquoted heredoc (standalone script runs directly in bash, not GitHub Actions — expansion works correctly) |

### Tests

- Update existing test to verify "Prepare clone URL" step exists
- Verify `CLONE_URL` is written to `$GITHUB_ENV`
- Verify deploy step uses `${{ env.CLONE_URL }}`

---

## Update: Slash Escaping Issue (2026-05-13)

### Problem

GitHub Actions escapes forward slashes when expanding `${{ env.CLONE_URL }}` in YAML. The clone URL becomes `https:\/\/github.com\/...` which git cannot parse.

### Root Cause

`${{ }}` expressions in GitHub Actions are sanitized to prevent injection. This escaping breaks URLs containing `://` and `/`.

### Fix

Avoid `${{ env.CLONE_URL }}` entirely. Instead:

1. The "Prepare clone URL" step writes `CLONE_URL` to `$GITHUB_ENV` (unchanged)
2. The deploy step uses an **unquoted heredoc** (`<<REMOTE`) so bash expands `${CLONE_URL}` as a regular env var — no GitHub Actions escaping
3. All other values (`DEPLOY_PATH`, `SSH_HOST`, etc.) are also passed as env vars in the step's `env:` block and expanded by bash
4. `${{ }}` expressions are only used in the `env:` block (where escaping doesn't happen), not inside the heredoc

### Files changed

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Deploy step: use unquoted heredoc, all values via env vars |
| `action.yml` | Same |
| `tests/Feature/InstallCommandTest.php` | Update test to check for `${CLONE_URL}` instead of `env.CLONE_URL` |

---

## Update 2: Heredoc expansion unreliable (2026-05-13)

### Problem

Even with `CLONE_URL` set via `$GITHUB_ENV` and all values in the `env:` block, the unquoted heredoc `<<REMOTE` still produced `ssh: Could not resolve hostname https` — meaning the URL was malformed when received by the server. The `\/` escaping visible in GitHub Actions logs may be applied at the YAML processing level, not just log masking.

### Fix

Abandon heredocs for the git clone step entirely. Use inline SSH commands instead:

```bash
ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} \
  "cd ~/${DEPLOY_PATH} && rm -rf releases/${RELEASE_DIR} && git clone --branch ${GIT_BRANCH_VAL} --single-branch --depth 1 '${CLONE_URL}' releases/${RELEASE_DIR}"

ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST} \
  "cd ~/${DEPLOY_PATH}/releases/${RELEASE_DIR} && ${REMOTE_PHP} /opt/cpanel/composer/bin/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader && rm -rf .git .github tests"
```

This avoids all heredoc expansion issues. The env vars are expanded by bash on the runner before SSH sends the command. The clone URL is single-quoted on the remote side to prevent the server's shell from reinterpreting it.

### Files changed

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Replace heredoc with inline SSH commands |
| `action.yml` | Same |

---

## Update 3: GitHub Actions escapes slashes in ALL `${{ }}` expressions (2026-05-13)

### Problem

Git clone still fails with `ssh: Could not resolve hostname https` even after moving `GIT_REPO` from `env:` block to inline script assignment.

The log shows the `${{ vars.GIT_REPO }}` expression is escaped **even when used directly in the `run:` script**:

```
Run GIT_REPO="https:\/\/github.com\/user\/repo.git"
```

The `\/` is **real escaping, not log masking**. Proof: `SSH_AUTH_SOCK: /tmp/ssh-XXX/agent.2098` has normal slashes (it's set by the runner, not from a `${{ }}` expression).

### Root Cause

GitHub Actions escapes forward slashes in ALL `${{ }}` expression substitutions — not just in `env:` blocks, but everywhere including `run:` scripts. Moving the expression from `env:` to `run:` doesn't help.

The escaped `\/` makes the URL invalid for git, which falls back to SSH protocol and tries to resolve hostname `https`.

### Fix

Strip backslash escaping after expression substitution using `tr -d '\\'`:

```yaml
- name: Prepare clone URL
  if: env.STRATEGY == 'git'
  env:
    GIT_TOKEN: ${{ secrets.GIT_TOKEN }}
  run: |
    GIT_REPO=$(echo "${{ vars.GIT_REPO }}" | tr -d '\\')
    if [ -n "${GIT_TOKEN}" ]; then
      echo "CLONE_URL=$(echo "${GIT_REPO}" | sed "s|https://github.com/|https://x-access-token:${GIT_TOKEN}@github.com/|")" >> $GITHUB_ENV
    else
      echo "CLONE_URL=${GIT_REPO}" >> $GITHUB_ENV
    fi
```

`tr -d '\\'` removes all backslash characters, turning `https:\/\/` back to `https://`. This is safe because valid git URLs never contain backslashes.

The `action.yml` also needs the same treatment for `${{ inputs.git-repo }}`, since action inputs may arrive pre-escaped if the calling workflow passes a `${{ vars.XXX }}` value.

### Files to change

| File | Change |
|---|---|
| `stubs/workflows/deploy.yml.stub` | Strip backslashes from `${{ vars.GIT_REPO }}` with `tr -d '\\'` |
| `action.yml` | Strip backslashes from `${{ inputs.git-repo }}` with `tr -d '\\'` |
| `tests/Feature/InstallCommandTest.php` | Update test assertion for new `tr -d` pattern |
