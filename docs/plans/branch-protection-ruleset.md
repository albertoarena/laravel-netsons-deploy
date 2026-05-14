# Plan: Branch Protection Ruleset for `main`

## Goal

Protect the `main` branch of `albertoarena/laravel-netsons-deploy` so that:
- No one can push directly to `main` (except the owner who can bypass)
- External contributors must fork and open PRs
- CI status checks must pass before merging
- Branch cannot be deleted or force-pushed

## Steps

### Step 1: Create CI workflow (`.github/workflows/ci.yml`)

A new GitHub Actions workflow that runs tests and linting on every push to `main` and on every PR targeting `main`.

**File:** `.github/workflows/ci.yml`

```yaml
name: CI

on:
  push:
    branches:
      - main
  pull_request:
    branches:
      - main

jobs:
  tests:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']

    name: PHP ${{ matrix.php }}

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run Pint (code style)
        run: composer lint

      - name: Run tests
        run: composer test
```

**Why this matrix:** The package supports PHP 8.2+ (per `composer.json`), so we test all supported versions.

**Job names** produced by the matrix: `PHP 8.2`, `PHP 8.3`, `PHP 8.4` — these are the status check context names needed in Step 2.

---

### Step 2: Create branch ruleset via GitHub API

**Command:**

```bash
gh api repos/albertoarena/laravel-netsons-deploy/rulesets \
  --method POST \
  --input - <<'EOF'
{
  "name": "main",
  "target": "branch",
  "enforcement": "active",
  "conditions": {
    "ref_name": {
      "include": ["refs/heads/main"],
      "exclude": []
    }
  },
  "bypass_actors": [
    {
      "actor_id": 5,
      "actor_type": "RepositoryRole",
      "bypass_mode": "always"
    }
  ],
  "rules": [
    {
      "type": "deletion"
    },
    {
      "type": "non_fast_forward"
    },
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 1,
        "dismiss_stale_reviews_on_push": true,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "required_review_thread_resolution": false,
        "automatic_copilot_code_review_enabled": false
      }
    },
    {
      "type": "required_status_checks",
      "parameters": {
        "strict_required_status_checks_policy": true,
        "required_status_checks": [
          { "context": "PHP 8.2" },
          { "context": "PHP 8.3" },
          { "context": "PHP 8.4" }
        ]
      }
    }
  ]
}
EOF
```

**What each section does:**

| Field | Purpose |
|---|---|
| `name: "main"` | Ruleset name displayed in Settings > Rulesets |
| `enforcement: "active"` | Rules are enforced (not just evaluated) |
| `conditions.ref_name.include` | Applies to `main` branch only |
| `bypass_actors` | `actor_id: 5` + `RepositoryRole` = **Repository admin** role, set to "Always allow" bypass — this lets you (the owner) push directly to main without a PR |
| `deletion` rule | Prevents deleting the `main` branch |
| `non_fast_forward` rule | Prevents force-pushes to `main` |
| `pull_request` rule | Requires a PR for merging; **1 required approval** (you must approve external PRs); dismisses stale reviews. You bypass this rule entirely as admin, so your own PRs don't need approval |
| `required_status_checks` rule | All 3 CI matrix jobs (`PHP 8.2`, `PHP 8.3`, `PHP 8.4`) must pass; `strict_required_status_checks_policy: true` means the branch must be up to date with `main` before merging |

---

### Step 3: Verify the ruleset

```bash
gh api repos/albertoarena/laravel-netsons-deploy/rulesets
```

This lists all rulesets to confirm it was created correctly.

---

## Order of Operations

1. **First** create and push the CI workflow file — the status checks won't exist in GitHub until the workflow has run at least once
2. **Then** create the ruleset — GitHub will accept the check names even before they've run, but they won't be enforceable until the workflow exists
3. **Verify** the ruleset is active

## Rollback

To delete the ruleset if something goes wrong:

```bash
# List rulesets to find the ID
gh api repos/albertoarena/laravel-netsons-deploy/rulesets

# Delete by ID
gh api repos/albertoarena/laravel-netsons-deploy/rulesets/{RULESET_ID} --method DELETE
```

## Notes

- The CI workflow file I already created at `.github/workflows/ci.yml` needs to be reverted and recreated cleanly as part of this plan
- No CODEOWNERS file is included in this plan (can be added later if desired)
- The `deploy-docs.yml` workflow is not included as a required check since it only runs on `website/**` path changes
