# Plan: Interactive workflow overwrite prompt

## Problem

When `php artisan netsons:install` is run and `.github/workflows/deploy.yml` already exists, the command silently skips regenerating it (just prints a message). Users must know to pass `--force` to overwrite. This feels broken — users expect the install command to regenerate the workflow, especially after reconfiguring env vars, seeders, etc.

The config file (`netsons-deploy.php`) already prompts interactively ("Config file already exists. Overwrite?"), but the workflow does not.

## Solution

Add an interactive confirmation prompt in `publishWorkflow()` when the workflow file exists and `--force` is not set, mirroring the pattern used for config overwrite.

### Changes

#### 1. `src/Commands/InstallCommand.php` — `publishWorkflow()` method (line ~456)

Current:
```php
if (File::exists($workflowPath) && ! $this->option('force')) {
    info('Workflow .github/workflows/deploy.yml already exists (use --force to overwrite).');
    return;
}
```

New:
```php
if (File::exists($workflowPath) && ! $this->option('force')) {
    if (! $this->input->isInteractive() || ! confirm('Workflow .github/workflows/deploy.yml already exists. Overwrite?', true)) {
        info('Keeping existing workflow file.');
        return;
    }
}
```

Key decisions:
- Default to **true** (yes) — unlike config overwrite which defaults to false. Rationale: users who just reconfigured env vars/seeders almost certainly want the workflow regenerated with their new settings. Skipping silently is the surprising behavior.
- Non-interactive mode without `--force`: keeps current skip behavior (no breaking change for CI).
- `--force` still bypasses the prompt entirely (no change).

#### 2. `tests/Feature/InstallCommandTest.php` — update + add tests

Tests to update:
- `'does not overwrite existing workflow without --force'` — this test runs non-interactive, so behavior is unchanged. Keep as-is.

Tests to add:
- Workflow is regenerated when `--force` is passed (already exists at line 180)
- Non-interactive mode without `--force` preserves existing workflow (already exists at line 168)
- **New:** Confirm the info message says "Keeping existing workflow file." in non-interactive skip scenario

## TDD Order

1. Write test: non-interactive without `--force` shows "Keeping existing workflow file." message
2. Update implementation in `publishWorkflow()`
3. Run full test suite, verify all pass
