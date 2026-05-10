# Fix Plan: netsons:check validation + netsons:install workflow publishing

## Problems

### 1. `netsons:check` validates credentials that only exist on GitHub

The `validate()` method in `FtpStrategy` and `GitStrategy` checks that `ssh.host`, `ssh.user`, `ftp.host`, `ftp.user`, `ftp.password`, and `git.repo` are set in the local config. These values come from `env()` calls, so they're always empty locally — the real values live in GitHub Secrets/Variables and are only available at deploy time.

This makes `netsons:check` produce false validation errors that confuse users who have correctly configured their GitHub repository.

### 2. `netsons:install` doesn't publish the workflow file

The install command publishes `config/netsons-deploy.php` but never creates `.github/workflows/deploy.yml`. The "Next steps" output tells the user to "Set up your deployment workflow" but doesn't offer to do it. The workflow stub exists at `stubs/workflows/deploy.yml.stub` and the service provider already registers it under the `netsons-deploy-workflows` publish tag, but the install command never triggers that publish.

## Fix Plan

### Fix 1: Rework `netsons:check` validation

**Strategy validation changes** (`FtpStrategy::validate()`, `GitStrategy::validate()`):
- Remove checks for credentials that are GitHub-only (SSH host/user, FTP host/user/password, git repo)
- Keep only checks for settings that are genuinely local config: `deploy_path`, `php_binary`
- These local settings have sensible defaults so they should rarely fail

**CheckCommand changes**:
- Replace the "Validation Issues" section with a "Checklist" approach:
  - Show local config status (strategy, deploy_path, php_binary, releases_keep) — always valid since they have defaults
  - Show whether `.github/workflows/deploy.yml` exists (actionable local check)
  - Show a reminder that credentials must be configured in GitHub Secrets/Variables (informational, not an error)

**Files to change**:
- `src/Strategies/FtpStrategy.php` — simplify `validate()`
- `src/Strategies/GitStrategy.php` — simplify `validate()`
- `src/Commands/CheckCommand.php` — rework `showValidation()` to show checklist + workflow file check
- `tests/Feature/CheckCommandTest.php` — update tests

### Fix 2: Add workflow publishing to `netsons:install`

**InstallCommand changes**:
- After publishing the config, ask the user (interactive) or automatically (non-interactive) publish the deploy workflow stub to `.github/workflows/deploy.yml`
- Replace `%%PLACEHOLDER%%` values in the published workflow with the selected strategy and sensible defaults from config
- Show a note about reviewing and customizing the workflow file

**Files to change**:
- `src/Commands/InstallCommand.php` — add `publishWorkflow()` method
- `tests/Feature/InstallCommandTest.php` — add tests for workflow publishing

### Documentation updates

After implementation, update:
- `README.md` — update the install flow description and netsons:check description
- `docs/configuration.md` — if relevant
- `website/src/content/docs/getting-started/installation.mdx` — reflect workflow publishing
- `website/src/content/docs/reference/artisan-commands.mdx` — update both command descriptions
