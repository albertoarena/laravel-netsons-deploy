# Plan: Branch Protection Ruleset for `main`

**Status: COMPLETED** (2026-05-14)

## Goal

Protect the `main` branch of `albertoarena/laravel-netsons-deploy` so that:
- No one can push directly to `main` (except the owner who can bypass)
- External contributors must fork and open PRs
- CI status checks must pass before merging
- Branch cannot be deleted or force-pushed

## Steps

### Step 1: Create CI workflow (`.github/workflows/ci.yml`) — DONE

A new GitHub Actions workflow that runs tests and linting on every push to `main` and on every PR targeting `main`.

**File:** `.github/workflows/ci.yml`
**Commit:** `7a601ca` — pushed to `main`
**First run:** All 3 jobs passed (PHP 8.2, 8.3, 8.4)

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

**Job names** produced by the matrix: `PHP 8.2`, `PHP 8.3`, `PHP 8.4` — these are the status check context names used in Step 2.

---

### Step 2: Create branch ruleset via GitHub API — DONE

**Ruleset ID:** `16381035`
**URL:** https://github.com/albertoarena/laravel-netsons-deploy/rules/16381035

**Command used:**

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
        "required_review_thread_resolution": false
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

**Note:** The `automatic_copilot_code_review_enabled` parameter was removed — it is not supported on free/public repos and causes a 422 validation error.

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

### Step 3: Verify — DONE

- Ruleset is active and visible in Settings > Rulesets
- CI workflow ran successfully on first push (all 3 PHP versions passed)

---

## Rollback

To delete the ruleset if needed:

```bash
gh api repos/albertoarena/laravel-netsons-deploy/rulesets/16381035 --method DELETE
```

To update the ruleset:

```bash
gh api repos/albertoarena/laravel-netsons-deploy/rulesets/16381035 \
  --method PUT \
  --input - <<'EOF'
{ ... updated JSON ... }
EOF
```

## Notes

- No CODEOWNERS file is included in this plan (can be added later if desired)
- The `deploy-docs.yml` workflow is not included as a required check since it only runs on `website/**` path changes
