# Plan: Remove dead stub scripts and unused publish tags

**Status: COMPLETED** (2026-08-14) — ships as **v1.13.0** (publish tags removed)

**Origin:** Codebase review (2026-08-14) found three parallel copies of deployment logic. Only one — the workflow stub / `action.yml` pair — is live and tested. The other two have silently drifted and now carry bugs that were already fixed months ago in the live copy.

**Date:** 2026-08-14

---

## Problem

The package ships four publish tags in `NetsonsDeployServiceProvider::registerPublishing()`. Only one of them (`netsons-deploy-config`) is used by `netsons:install`. The other three publish files that nothing reads, that no test covers, and that no page in `README.md`, `docs/`, or `website/src/content/docs/` mentions.

Because they are unreferenced, they were never updated when the live workflow was fixed. Each now hands a user a regression that was already closed:

### A. Stale Composer path defaults

`configurable-remote-composer.md` (completed) standardised the remote Composer path on `/usr/local/bin/composer`. Three places still say `/opt/cpanel/composer/bin/composer`, contradicting `config/netsons-deploy.php:23`, `action.yml:29`, CLAUDE.md, and every docs page:

| File | Line | Severity |
|---|---|---|
| `stubs/scripts/deploy-git.sh` | 20 (comment), 29 (`COMPOSER_PATH` default) | Live for anyone using the published scripts — reproduces the original `Could not open input file` failure |
| `src/Commands/InstallCommand.php` | 485 (`?? '/opt/cpanel/...'` fallback) | Practically unreachable — `mergeConfigFrom` always supplies the key — but wrong, and contradicts CLAUDE.md |
| `stubs/workflows/deploy.yml.stub` | 34 (`# e.g. /opt/cpanel/...` comment) | Cosmetic, but misleads users editing the generated workflow |

### B. `stubs/scripts/*.sh` — six unmaintained shell scripts

~400 lines across `deploy-ftp.sh`, `deploy-git.sh`, `post-deploy.sh`, `setup-ssh.sh`, `switch-release.sh`, `cleanup-releases.sh`. Nothing in `action.yml` or `stubs/workflows/deploy.yml.stub` sources them; the only reference in the entire codebase is the publish tag at `NetsonsDeployServiceProvider.php:54`. Zero test coverage.

`deploy-git.sh` has drifted furthest and would reintroduce three already-fixed bugs:

1. `COMPOSER_PATH` defaults to the wrong path (item A)
2. No `mkdir -p bootstrap/cache` before `composer install` — the failure fixed in `9f99163` (`The bootstrap/cache directory must be present and writable`)
3. No `ssh_retry` / `scp_retry` wrappers — none of the SSH retry resilience work (`a1ec89b`, `abecbde`) applies

### C. `netsons-deploy-workflows` publishes raw `.stub` files

`NetsonsDeployServiceProvider.php:46` maps `stubs/workflows/` to `.github/workflows/`. Running it copies `deploy.yml.stub` and `test.yml.stub` verbatim, `%%STRATEGY%%` / `%%REMOTE_PHP%%` / `%%ENV_MAPPING%%` placeholders intact. GitHub ignores non-`.yml` files so nothing breaks, but the output is never usable. `netsons:install` → `publishWorkflow()` is the real path: it reads the stub, substitutes placeholders, and writes `.github/workflows/deploy.yml`.

### D. `stubs/htaccess/` — same pattern, discovered during research

Not part of the original a+b+c brief, but the same class of defect and cheap to fix in the same pass.

There are three copies of the root `.htaccess` rules:

| Copy | State |
|---|---|
| `stubs/workflows/deploy.yml.stub:365-371` (inline heredoc) | **Live and correct** — has the `RewriteCond %{REQUEST_URI} !^/public/` guard |
| `src/Services/HtaccessGenerator::generateRoot()` | Correct, but called by no command — only by its own unit test |
| `stubs/htaccess/root.stub` | **Stale** — missing the guard, i.e. the infinite rewrite loop that backlog item B20 fixed |

`stubs/htaccess/public.stub` is byte-identical to `HtaccessGenerator::generatePublic()`, so only the root copy has drifted — for now. Three copies with no test tying them together will drift again.

## Root cause

Publishable assets with no consumer and no test. The live workflow is protected by 293 tests; these files are protected by nothing, so every fix to the live path silently widens the gap.

## Approach

Delete what has no consumer. For the one asset that should keep a consumer (`.htaccess`), collapse the copies to one and bind it with a test so it cannot drift again.

---

## Changes

### A1 — Correct the Composer path defaults

- `src/Commands/InstallCommand.php:485` — fallback `/opt/cpanel/composer/bin/composer` → `/usr/local/bin/composer`
- `stubs/workflows/deploy.yml.stub:34` — comment → `# e.g. /usr/local/bin/composer`
- `stubs/scripts/deploy-git.sh` — resolved by B1 (file deleted)

### B1 — Delete `stubs/scripts/` and its publish tag

- Delete all six `stubs/scripts/*.sh`
- Remove the `netsons-deploy-scripts` block from `NetsonsDeployServiceProvider::registerPublishing()` (lines 53-55)

### C1 — Remove the `netsons-deploy-workflows` publish tag

- Remove the block at `NetsonsDeployServiceProvider.php:45-47`
- **Keep** `stubs/workflows/*.stub` — they are the source of truth for `InstallCommand::publishWorkflow()`

### D1 — Single source of truth for `.htaccess`

- Add a `%%ROOT_HTACCESS%%` placeholder to `deploy.yml.stub`, replacing the hardcoded heredoc body at lines 366-370
- `InstallCommand::publishWorkflow()` substitutes it from `HtaccessGenerator::generateRoot()`, indented to match the surrounding YAML heredoc
- Delete `stubs/htaccess/` and the `netsons-deploy-htaccess` tag (`NetsonsDeployServiceProvider.php:49-51`)
- `HtaccessGenerator` becomes live code with a real caller

Indentation is the one fiddly part: the heredoc body sits at 10 spaces inside `run: |`. The substitution must re-indent each line of the generated string, and the test in D2 pins the exact rendered output.

### Files to change

| File | Change |
|---|---|
| `src/NetsonsDeployServiceProvider.php` | Remove three publish tags; keep `netsons-deploy-config` |
| `src/Commands/InstallCommand.php` | Fix Composer fallback; substitute `%%ROOT_HTACCESS%%` |
| `stubs/workflows/deploy.yml.stub` | Fix comment; replace inline htaccess with placeholder |
| `stubs/scripts/*.sh` | Delete (6 files) |
| `stubs/htaccess/*.stub` | Delete (2 files) |
| `tests/Unit/ServiceProviderTest.php` | **New** — publish tag assertions |
| `tests/Feature/InstallCommandTest.php` | Composer fallback + htaccess-from-generator tests |
| `CLAUDE.md` | Refresh Directory Structure section |

---

## Tests (TDD — write these first, watch them fail)

### A2 — Composer fallback
In `tests/Feature/InstallCommandTest.php`:
- Set `config(['netsons-deploy.composer_binary' => null])`, run `netsons:install`, assert the generated workflow contains `REMOTE_COMPOSER: '/usr/local/bin/composer'`
- Assert the generated workflow contains no occurrence of `/opt/cpanel/`

### B2/C2 — Publish tags
New `tests/Unit/ServiceProviderTest.php`, using `ServiceProvider::pathsToPublish(NetsonsDeployServiceProvider::class, $tag)`:
- `netsons-deploy-config` returns a non-empty array (unchanged)
- `netsons-deploy-scripts`, `netsons-deploy-workflows`, `netsons-deploy-htaccess` each return an empty array

### D2 — htaccess single source
In `tests/Feature/InstallCommandTest.php`:
- Existing test `generates root htaccess with RewriteCond to prevent rewrite loop` (line 718) must still pass unchanged — it is the regression guard for B20
- **New:** every non-empty line of `(new HtaccessGenerator)->generateRoot()` appears in the generated workflow, proving the generator is the source
- **New:** the workflow contains no literal `%%ROOT_HTACCESS%%` after generation

---

## Execution order

1. Write all tests from A2, B2/C2, D2 — confirm they fail for the right reasons
2. A1 — Composer path defaults
3. B1 — delete `stubs/scripts/` + tag
4. C1 — remove workflows tag
5. D1 — htaccess placeholder + delete `stubs/htaccess/` + tag
6. `composer test` (expect 293 + ~7 new, all green) and `composer lint`
7. CLAUDE.md Directory Structure refresh

## Risks and edge cases

| Risk | Assessment |
|---|---|
| Removing publish tags breaks a user's `vendor:publish` | Technically a BC break. All three are undocumented — absent from `README.md`, `docs/`, and the website — and the scripts they publish are buggy. Anyone who already published keeps their copy; only re-publishing stops working. Ship as **v1.13.0** (next minor after v1.12.0), not a patch, and note the removal in the release notes. |
| Someone actively uses the published scripts | Then they are running the pre-`a1ec89b` deploy path with no SSH retry and the `bootstrap/cache` bug. Deleting is a fix, not a regression. |
| htaccess indentation breaks the YAML heredoc | Contained by D2 pinning exact rendered output, plus the existing B20 regression test. If it proves awkward, fall back to leaving the heredoc inline and instead deleting `HtaccessGenerator` + its test — one copy either way. |
| `action.yml` also has an inline root `.htaccess` (line 359-365) | Correct there, and out of scope — a composite action cannot call PHP. It stays a deliberate second copy; D2 does not cover it. |

## Out of scope

- The other review findings: CI Laravel-version matrix, `::add-mask::` on `CLONE_URL`, splitting `InstallCommand` (750 lines) into a `WorkflowGenerator` service. Separate plans if wanted.
- `docs/plans/sqlite-support.md` — parked.

## Implementation notes (2026-08-14)

Executed as planned, all four items (A–D), TDD throughout. 11 new tests; suite went 293 → 304 passing, Pint clean.

- `ServiceProviderTest` needs a booted app for `pathsToPublish()` to return anything, so the file opts in with `uses(TestCase::class)` — `tests/Pest.php` only binds `TestCase` to `Feature`. Without that, the "tag removed" assertions would have passed vacuously; the `netsons-deploy-config` assertion is the guard that proves boot happened.
- The D1 indentation worked exactly as predicted. Verified beyond string assertions by generating a real workflow and parsing it with a YAML parser: the document is valid, and the rendered heredoc body lands at column 0 with its `HTACCESS` terminator unindented, identical to the previous hardcoded output plus the generator's explanatory comment.
- The only surviving `/opt/cpanel/` mentions are in `docs/configuration.md:50` and `configuration.mdx:48`, where the path is correctly cited as an *example alternative* to the default. Left as-is.

## Docs to update after implementation

Per the CLAUDE.md checklist. `README.md`, `docs/`, and `website/src/content/docs/` need **no changes** — verified that none of them reference `stubs/`, `scripts/`, or any publish tag. Only `CLAUDE.md`'s Directory Structure section needs a refresh; it is already stale (lists a `src/Stubs/` directory that does not exist, and omits `DeployConfigManager.php` and `EnvCommand.php`).
