# Plan: Workflow customisation gaps

## Status: Draft

## Context

When a project with a hand-tuned `deploy.yml` runs `netsons:install` and overwrites the workflow, project-specific customisations are lost. Real-world projects commonly have:

1. **Prerender step for SEO** — runs a local Laravel server + Playwright to prerender public pages, then deploys the static HTML
2. **Public asset syncing** — copies favicon, robots.txt, images, error pages, prerendered HTML, and storage symlink to the deploy-base `public/` directory
3. **GitHub Variables with defaults** — e.g. `${{ vars.FEATURE_ENABLED || 'true' }}`, `${{ vars.AI_MODEL || 'default-model' }}`
4. **`.active_release` tracking file** — writes the active timestamp to a file for external monitoring
5. **Localised Slack messages** — deploy messages in languages other than English
6. **`public/storage` symlink** — `ln -s ../shared/storage/app/public public/storage` for user uploads

The generated workflow only copies `public/.htaccess` and `public/build/` during release activation, missing all other public assets. It also doesn't support pre-deploy or post-build hooks where custom steps could be injected.

## Features lost in overwrite

### Critical (breaks the app)

| Feature | What was lost | Impact |
|---------|---------------|--------|
| Public asset copy | favicon, robots.txt, images dir, error pages, prerendered pages | 404s for static assets, broken SEO, broken error pages |
| `public/storage` symlink | User-uploaded files not accessible via web | Broken images/downloads |
| GitHub Variable defaults | `${{ vars.X \|\| 'default' }}` pattern for optional vars | Vars without defaults become empty strings |

### Important (degrades functionality)

| Feature | What was lost | Impact |
|---------|---------------|--------|
| Prerender step | SEO prerendering with Playwright | Pages not indexed properly by search engines |
| Localised Slack messages | Non-English deploy messages | Cosmetic but signals the tool didn't understand the project |
| `.active_release` file | External monitoring can't detect which release is live | Monitoring gap |

### Minor / cosmetic

| Feature | What was lost | Impact |
|---------|---------------|--------|
| Workflow name | "Deploy" vs "Deploy to Netsons" | Cosmetic |
| Step names | Original naming conventions | Cosmetic |

## Analysis: Root cause

The generated workflow is **opinionated and rigid** — it has a fixed set of steps with no extension points. When it overwrites a hand-tuned workflow, everything outside the template is lost. The `netsons-deploy.json` config handles env vars, seeders, custom commands, and notifications, but not:

1. Custom workflow steps (pre-build, post-build, pre-deploy, post-deploy)
2. Public asset management beyond `.htaccess` and `build/`
3. GitHub Variable defaults
4. Release tracking files
5. Storage symlink in deploy-base public dir

## Proposed improvements

### P1: Public assets configuration (high value, moderate effort)

Add a `public_assets` section to `netsons-deploy.json` that lists files/dirs to copy from the release to deploy-base `public/`:

```json
{
  "public_assets": {
    "files": [
      "favicon.ico",
      "favicon.svg",
      "apple-touch-icon.png",
      "robots.txt",
      "401.shtml",
      "403.shtml"
    ],
    "directories": [
      "build",
      "images",
      "prerendered"
    ],
    "storage_symlink": true
  }
}
```

The installer could auto-detect these by scanning `public/` in the project. The generated workflow would include copy commands for each configured asset.

**Default behaviour:** Always copy `build/` and `.htaccess` (current). Add `storage_symlink: true` as default.

### P2: Custom workflow steps / hooks (high value, high effort)

Allow users to define custom steps that get injected at specific points in the workflow:

```json
{
  "hooks": {
    "post_build": [
      {
        "name": "Prerender public pages for SEO",
        "run": "npx playwright install chromium --with-deps\nphp artisan serve --port=8001 &\nSERVER_PID=$!\nsleep 3\nnode scripts/prerender.mjs http://localhost:8001\nkill $SERVER_PID"
      }
    ],
    "post_deploy": [
      {
        "name": "Write active release file",
        "run": "echo '${{ steps.release.outputs.dir }}' > ~/${{ vars.DEPLOY_PATH }}/.active_release"
      }
    ]
  }
}
```

Hook points:
- `post_checkout` — after checkout, before build
- `post_build` — after asset build, before SSH setup (runs on runner)
- `pre_deploy` — after SSH setup, before deploy step (runs on runner)
- `post_deploy` — after release activation, before cleanup (runs on server via SSH)

This is the most flexible approach but also the most complex. Steps would be injected as raw YAML into the workflow template at marked positions.

**Alternative: fenced blocks.** Instead of storing steps in JSON, use markers in the workflow:

```yaml
# --- BEGIN CUSTOM: post_build ---
- name: Prerender public pages for SEO
  run: ...
# --- END CUSTOM: post_build ---
```

On regeneration, the installer would parse and preserve fenced blocks while replacing everything outside them. This avoids storing YAML in JSON but adds parsing complexity.

### P3: GitHub Variable defaults (low effort, quick win)

Support default values for GitHub Variables in `netsons-deploy.json`:

```json
{
  "env_variables": {
    "FEATURE_ENABLED": {
      "source": "variable",
      "default": "true"
    },
    "AI_MODEL": {
      "source": "variable",
      "default": "default-model"
    }
  }
}
```

Generated as `${{ vars.FEATURE_ENABLED || 'true' }}` in the workflow. This is a new variable type alongside secret-backed and static.

### P4: Slack message localisation (low effort, quick win)

Add a `locale` or `notifications.language` setting:

```json
{
  "notifications": {
    "slack_webhook_secret": "SLACK_WEBHOOK_DEBUG",
    "language": "en"
  }
}
```

Or just allow custom message templates:

```json
{
  "notifications": {
    "slack_webhook_secret": "SLACK_WEBHOOK_DEBUG",
    "success_message": ":white_check_mark: Deploy $ENV succeeded — release $RELEASE",
    "failure_message": ":x: Deploy $ENV failed — $RUN_URL"
  }
}
```

### P5: Release tracking file (low effort, quick win)

Add an option to write an `.active_release` file:

```json
{
  "release_tracking": true
}
```

Generates: `echo '${{ steps.release.outputs.dir }}' > ~/${{ vars.DEPLOY_PATH }}/.active_release`

### P6: Workflow merge strategy (high value, high effort)

Instead of a binary overwrite/skip, offer a **merge** option:

```
Workflow .github/workflows/deploy.yml already exists.
  [o] Overwrite (regenerate from scratch)
  [m] Merge (update generated sections, keep custom steps)
  [s] Skip (keep existing)
```

Merge would:
1. Parse the existing workflow into sections (using comment markers)
2. Regenerate the template sections (env block, standard steps)
3. Preserve custom steps that don't match any template step
4. Show a diff for review

This is the most ambitious approach and would solve the root cause, but is complex to implement reliably.

## Recommended priority

1. **P1: Public assets** — fixes the most common breakage, auto-detectable
2. **P5: Release tracking** — trivial to implement, useful for monitoring
3. **P3: Variable defaults** — small change, enables a common pattern
4. **P4: Slack localisation** — quick win, custom templates are flexible
5. **P2: Custom hooks** — high value but needs careful design
6. **P6: Workflow merge** — ideal long-term solution but significant complexity

## Open questions

- Should custom hooks be stored in `netsons-deploy.json` (portable, versionable) or as fenced blocks in the workflow (easier to edit manually)?
- For P1, should the installer auto-detect public assets by scanning `public/` and offering a multiselect? Or keep it manual?
- For P6, is full merge realistic given that workflows can be arbitrarily customised? A simpler "preserve fenced blocks" approach may be more reliable.
