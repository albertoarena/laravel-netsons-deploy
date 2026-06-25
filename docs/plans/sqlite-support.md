# Plan: SQLite database support

**Origin:** Deploying lightweight / demo Laravel sites to Netsons shared hosting where spinning up a MySQL database is overkill. The package currently assumes a server-side MySQL database reached through `DB_*` credentials and has no awareness of SQLite. A SQLite app deployed today would lose its database on every release (the `.sqlite` file lives inside the release directory, which is replaced and pruned) and would fail its first `artisan migrate` because the file does not exist yet.

**Date:** 2026-06-25

---

## Problem

This is a **release-based** deployment. Each deploy creates `releases/YYYYMMDDHHMMSS/` and, after success, repoints the `current` symlink. Only `keep` (default 5) releases survive — older ones are pruned.

Laravel's default SQLite location is `database/database.sqlite`, **inside the application directory**. With release-based deploys that means:

1. **Data loss** — the file lives in `releases/<ts>/database/database.sqlite`. The next deploy creates a fresh release directory, and pruning eventually deletes the old one. The demo's data vanishes.
2. **First-deploy failure** — `.sqlite` files are gitignored and excluded from FTP upload (`**/storage/logs/**`, `**/.env` filters already exist; SQLite is similar). On a clean release the file is absent, so `artisan migrate --force` aborts with *"database does not exist"*.
3. **No driver awareness** — `config/netsons-deploy.php`, `DeployConfigManager`, and `InstallCommand` treat every app as MySQL. `parseEnvExample()` flags `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` as required secret-backed variables (`DeployConfigManager.php:103`). For SQLite those credentials are meaningless and would prompt the user for secrets that do not exist.

The package already solves the exact same persistence problem for `.env` and `storage/` by keeping them in a persistent `shared/` directory and symlinking them into each release (`stubs/workflows/deploy.yml.stub:282-299`, `stubs/scripts/post-deploy.sh:43-70`). SQLite should reuse that mechanism.

## Solution: treat the SQLite file as a shared resource

Make the database **driver** a first-class, explicit choice (`mysql` default, `sqlite` opt-in), and when `sqlite` is selected, manage the database file as a shared resource exactly like `.env` and `storage/`:

```
~/public_html/
├── shared/
│   ├── .env                              # already shared
│   ├── storage/                          # already shared
│   └── database/
│       └── database.sqlite               # NEW — persistent SQLite file
└── releases/
    └── 20260625120000/
        └── database/
            └── database.sqlite -> ~/public_html/shared/database/database.sqlite   # symlinked per release
```

Mechanism (runs in the existing **Setup shared resources** step, *before* migrations):

1. `mkdir -p shared/database` and `touch shared/database/database.sqlite` if absent (idempotent — survives every deploy).
2. Symlink `releases/<ts>/database/database.sqlite` → `shared/database/database.sqlite` (mirrors the `storage` symlink).
3. Set `DB_CONNECTION=sqlite` and `DB_DATABASE=<absolute path to the shared file>` in the shared `.env` (replace-or-append, so it works even when `.env.example` lacks those lines). An **absolute** path is used so `config:cache` bakes a stable value independent of which release is active.

Because the file lives in `shared/`, it persists across deploys (the user's chosen behaviour — **no reset on deploy**). Migrations run against the persistent file; only new migrations apply on subsequent deploys.

When the driver is `mysql` (the default), **nothing changes** — no SQLite blocks are emitted, so existing users see zero diff.

### Why absolute `DB_DATABASE` *and* a symlink

- The **absolute `DB_DATABASE`** guarantees correctness on its own: Laravel opens the shared file directly regardless of release, and a cached config stays valid.
- The **symlink** is the "shared resource" pattern the rest of the package uses; it keeps `php artisan` commands that assume the default relative `database/database.sqlite` path working, and makes the layout consistent with `storage/`.

---

## Changes

### Summary

| # | Item | Files | Type |
|---|------|-------|------|
| H1 | Add `database` section (driver + shared path) to config | `config/netsons-deploy.php` | Enhancement |
| H2 | Add `database` to `DeployConfigManager` defaults + `setDatabaseConnection()` + SQLite-aware env parsing | `src/Services/DeployConfigManager.php` | Enhancement |
| H3 | Prompt for database driver during install (+ `--database` option, validation); skip DB secrets when SQLite | `src/Commands/InstallCommand.php` | Enhancement |
| H4 | Emit the SQLite shared-resource block into the generated workflow | `src/Commands/InstallCommand.php`, `stubs/workflows/deploy.yml.stub` | Enhancement |
| H5 | Conditional SQLite setup in the reusable Action | `action.yml` | Enhancement |
| H6 | Conditional SQLite setup in the standalone post-deploy script | `stubs/scripts/post-deploy.sh` | Enhancement |
| H7 | Exclude committed `*.sqlite` files from FTP upload (prevent clobbering the shared DB) | `action.yml`, `stubs/workflows/deploy.yml.stub` | Safeguard |
| H8 | Show database driver (+ SQLite path) in `netsons:check` | `src/Commands/CheckCommand.php` | Enhancement |
| H9 | Tests (config, manager, install, workflow output, action, script, regression) | `tests/Unit/*`, `tests/Feature/*` | Test |
| H10 | Documentation (README, docs/ incl. new SQLite guide, website/) | see Docs section | Docs |

---

### H1: `config/netsons-deploy.php` — database section

Add after the `deploy_path` key (before `ftp`):

```php
// Database driver: 'mysql' (default) or 'sqlite'.
// 'sqlite' keeps a persistent database file in shared/database/ and
// symlinks it into each release — suitable for demo / lightweight sites.
'database' => [
    'connection' => env('NETSONS_DB_CONNECTION', 'mysql'),

    // SQLite-specific settings (only used when connection is 'sqlite')
    'sqlite' => [
        // Path to the shared SQLite file, relative to the deploy path.
        'shared_path' => env('NETSONS_SQLITE_PATH', 'shared/database/database.sqlite'),
    ],
],
```

### H2: `src/Services/DeployConfigManager.php`

**Defaults (line ~9):** add `'database' => ['connection' => 'mysql']` to `DEFAULTS`.

**New setter** (mirrors `setEnvaudit()`):

```php
public function setDatabaseConnection(string $connection): void
{
    $data = $this->read();
    $data['database'] = ['connection' => $connection];
    $this->write($data);
}
```

**SQLite-aware `parseEnvExample()`** — add an optional `string $connection = 'mysql'` parameter. When `$connection === 'sqlite'`, drop credential keys from the secret suggestions so the installer does not ask for non-existent DB secrets:

```php
// after building $secretPatterns
if ($connection === 'sqlite') {
    $secretPatterns = array_values(array_diff(
        $secretPatterns,
        ['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']
    ));
}
```

`DB_*` is already in `$excludePrefixes`, so no SQLite `DB_*` key leaks into the static suggestions — `DB_CONNECTION`/`DB_DATABASE` are owned entirely by the dedicated SQLite block (H4).

### H3: `src/Commands/InstallCommand.php` — driver prompt + `--database` option

**Signature** — add a `--database` option alongside `--strategy` for non-interactive / scripted installs:

```php
protected $signature = 'netsons:install
                        {--strategy= : Deployment strategy (ftp or git)}
                        {--database= : Database driver (mysql or sqlite)}
                        {--force : Overwrite existing config file}';
```

**Resolution + validation** — resolve the driver once (option → interactive prompt → existing config → default), validating any explicit value so a typo fails fast instead of silently emitting a broken workflow:

```php
$databaseOption = $this->option('database');
if ($databaseOption !== null && ! in_array($databaseOption, ['mysql', 'sqlite'], true)) {
    error("Invalid --database value \"{$databaseOption}\". Use 'mysql' or 'sqlite'.");
    return self::FAILURE;
}
```

In `collectDeployJson()`, after strategy secrets are shown and before `parseEnvExample()` is called, resolve and persist:

```php
$databaseConnection = $databaseOption
    ?? ($this->input->isInteractive()
        ? select('Which database driver?', ['mysql', 'sqlite'], $existingConfig['database']['connection'] ?? 'mysql')
        : ($existingConfig['database']['connection'] ?? 'mysql'));
$manager->setDatabaseConnection($databaseConnection);

if ($databaseConnection === 'sqlite') {
    note('SQLite selected — a persistent database file will be kept in shared/database/ and symlinked into each release. No DB_* secrets are required.');
}
```

Pass the driver into detection:

```php
$detected = DeployConfigManager::parseEnvExample($envExamplePath, $databaseConnection);
```

`publishWorkflow()` reads `$deployConfig['database']['connection']` and injects the new placeholder (H4). Note `collectDeployJson()` early-returns in non-interactive mode (`InstallCommand.php:113`); the driver must therefore also be persisted on the non-interactive path so `--database sqlite` works headlessly — fold the `setDatabaseConnection()` call into the shared resolution point that runs before that guard, or persist it in `handle()` directly after validation.

### H4: SQLite shared-resource block in the workflow

**`stubs/workflows/deploy.yml.stub`** — inside the existing **Setup shared resources** step (the quoted `<<'REMOTE'` heredoc, so `$HOME` expands *on the server*), after the `.env` symlink (line ~297) and before the closing `REMOTE`, add the placeholder:

```bash
%%DATABASE_SETUP%%
```

**`src/Commands/InstallCommand.php`** — add a `generateDatabaseSetupBlock(string $connection): string` that returns `''` for `mysql` and, for `sqlite`, the block below (note the 12-space indent matching the heredoc body), then `str_replace('%%DATABASE_SETUP%%', ...)` in `publishWorkflow()`:

```bash
            # ── SQLite shared database ──────────────────────────────────
            SQLITE_PATH="$HOME/${{ vars.DEPLOY_PATH }}/shared/database/database.sqlite"
            mkdir -p "$(dirname "$SQLITE_PATH")"
            [ -f "$SQLITE_PATH" ] || touch "$SQLITE_PATH"
            mkdir -p ${RELEASE_PATH}/database
            ln -sfn "$SQLITE_PATH" ${RELEASE_PATH}/database/database.sqlite
            ENV_FILE=${DEPLOY_BASE}/shared/.env
            if grep -q '^DB_CONNECTION=' "$ENV_FILE"; then
              sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|" "$ENV_FILE"
            else
              echo "DB_CONNECTION=sqlite" >> "$ENV_FILE"
            fi
            if grep -q '^DB_DATABASE=' "$ENV_FILE"; then
              sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${SQLITE_PATH}|" "$ENV_FILE"
            else
              echo "DB_DATABASE=${SQLITE_PATH}" >> "$ENV_FILE"
            fi
```

Robustness notes baked into the block:
- `grep … || echo >>` so it works whether or not `.env.example` carries `DB_CONNECTION`/`DB_DATABASE` lines.
- `mkdir -p ${RELEASE_PATH}/database` guards the FTP path where the runner-built tree may already contain `database/`, and the git path where it always does — harmless either way.
- Runs before the **Run migrations** step (line ~374), so the file and `.env` are ready when `artisan migrate` executes.

### H5: `action.yml`

Add an input:

```yaml
  database:
    description: 'Database driver: mysql or sqlite'
    default: 'mysql'
```

In the **Setup shared resources** step, after the `.env`/`storage` symlinks, add a guarded block (action steps are plain bash, so a runtime `if` replaces the templating used in the generated workflow):

```bash
if [ "${{ inputs.database }}" = "sqlite" ]; then
  # …same SQLite_PATH / touch / symlink / sed-or-append logic as H4…
fi
```

### H6: `stubs/scripts/post-deploy.sh`

Add an optional `DB_DRIVER="${DB_DRIVER:-mysql}"` variable (documented in the header alongside `RUN_MIGRATIONS`). Inside the remote heredoc, after the storage symlink (line ~63) and before migrations (line ~72), add the same `if [ "${DB_DRIVER}" = "sqlite" ]; then … fi` SQLite block.

### H7: FTP upload exclusion — never overwrite the shared SQLite file

The FTP strategy syncs the runner's built tree to the release directory. If a developer has ever committed or locally generated a `database/database.sqlite` (common — it is the Laravel default, and not every project gitignores it), FTP would upload it and, on a subsequent deploy, the symlink target could be clobbered or a stale file shadowed. The deploy already excludes `**/.env`, `**/.env.*`, `**/storage/logs/**`.

Add `**/database/*.sqlite` (and `**/database/*.sqlite-journal`) to the `exclude:` list of the `SamKirkland/FTP-Deploy-Action` step in **both**:

- `action.yml` (the `exclude:` block near `**/storage/logs/**`, ~line 229)
- `stubs/workflows/deploy.yml.stub` (the matching FTP step)

This is unconditional and harmless for MySQL apps (they have no such file). It guarantees the server-side shared file is the single source of truth.

### H8: `src/Commands/CheckCommand.php`

Add rows to the settings table so users can confirm the active driver and, for SQLite, the persistent file location:

```php
$connection = $deployConfig['database']['connection'] ?? 'mysql';
// ... in the rows array:
['Database', $connection],
```

When `$connection === 'sqlite'`, append a second row with the resolved shared path (from config `database.sqlite.shared_path`, default `shared/database/database.sqlite`) and a one-line `note()` reminding the user the file persists across deploys. Reads from `netsons-deploy.json` via `DeployConfigManager`, consistent with how other JSON-backed settings are surfaced.

---

## Execution order (TDD)

1. **Tests first** (H9) — the full matrix below.
2. **Implement** H1 → H2 → H3 → H4 → H5 → H6 → H7 → H8 until green.
3. `composer test` and `composer lint` (fix with `composer lint:fix`).
4. **Docs** (H10).
5. Confidentiality sweep (no real hosts/paths/repo names — use `user/repo`, `public_html`).

### H9: Test matrix

| File | Cases |
|---|---|
| `tests/Unit/ConfigTest.php` | `database.connection` defaults to `mysql`; `database.sqlite.shared_path` default is `shared/database/database.sqlite`; section is an array with the expected keys. |
| `tests/Unit/DeployConfigManagerTest.php` | `defaults()` includes `database` ⇒ `mysql`; `setDatabaseConnection('sqlite')` persists and round-trips through `read()`; `setDatabaseConnection('mysql')` overwrites; `parseEnvExample($path,'sqlite')` omits `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` from `secret`; `parseEnvExample($path,'mysql')` **keeps** them (regression); default-arg call (no driver) behaves as `mysql` (back-compat for any existing callers). |
| `tests/Feature/InstallCommandTest.php` | **sqlite path:** generated `deploy.yml` contains `shared/database/database.sqlite`, `DB_CONNECTION=sqlite`, the `ln -sfn` symlink line, and the `grep …\|\| echo >>` append guard. **mysql path (regression):** generated `deploy.yml` contains **none** of the SQLite markers — assert the diff against the current golden output is empty. **`--database` option:** non-interactive `--database=sqlite` persists to `netsons-deploy.json` and produces the SQLite workflow; `--database=invalid` returns `FAILURE` and writes nothing. |
| `tests/Feature/InstallCommandTest.php` (FTP exclude, H7) | Generated `deploy.yml` FTP step `exclude:` list contains `**/database/*.sqlite` regardless of driver. |
| `tests/Feature/CheckCommandTest.php` | With `database.connection=sqlite` in JSON, `netsons:check` output shows the `Database` row as `sqlite` and the shared-path row; with `mysql` (or absent JSON) it shows `mysql` and no path row. |
| `tests/Unit/PostDeployScriptTest.php` *(new)* | Static assertions on `stubs/scripts/post-deploy.sh`: defaults `DB_DRIVER` to `mysql`; contains a `[ "${DB_DRIVER}" = "sqlite" ]` guard; the SQLite branch creates `shared/database`, `touch`es the file, symlinks it, and writes `DB_CONNECTION`/`DB_DATABASE`. (Mirrors the existing static-stub testing style used for other scripts.) |
| `action.yml` coverage | Assert (in a unit test reading the YAML) that the `database` input exists with default `mysql`, the shared-resources step contains the `inputs.database = sqlite` guard, and the FTP exclude list contains `**/database/*.sqlite`. |

---

## Docs (H10 — all three locations kept in sync)

| Location | Update |
|---|---|
| `README.md` | Features list: "First-class SQLite support for demo/lightweight sites (persistent, shared)". Add `database.connection` to the config snippet; mention the `netsons:install --database=sqlite` option in the artisan-commands section. |
| `docs/configuration.md` | Document the `database` config section and the `NETSONS_DB_CONNECTION` / `NETSONS_SQLITE_PATH` env vars. |
| `docs/ftp-strategy.md`, `docs/git-strategy.md` | Add `shared/database/database.sqlite` to the server directory-structure diagrams; note the `*.sqlite` FTP exclusion. |
| `docs/troubleshooting.md` | SQLite gotchas: file permissions on shared hosting, "database does not exist" pre-fix symptom, confirming the symlink, demo data persistence, why a committed `database.sqlite` is excluded from FTP. |
| `docs/database.md` *(new — committed, not optional)* | "Using SQLite on Netsons" guide: when to choose SQLite vs MySQL, how shared-file persistence works, the symlink + absolute-path mechanism, first-deploy seeding for demo data (ties into existing first-deploy seeders), and how to reset a demo manually (ssh + `rm shared/database/database.sqlite` then redeploy). Add to the docs index/nav. |
| `website/src/content/docs/reference/configuration.mdx` | Mirror `docs/configuration.md`. |
| `website/src/content/docs/reference/artisan-commands.mdx` | Document the `--database` install option. |
| `website/src/content/docs/reference/github-action.mdx` | Document the new `database` action input. |
| `website/src/content/docs/strategies/ftp.mdx`, `strategies/git.mdx` | Mirror directory-structure + FTP-exclusion updates. |
| `website/src/content/docs/guides/using-sqlite.mdx` *(new)* | Mirror `docs/database.md`; add to the Starlight sidebar config. |
| `website/src/content/docs/help/troubleshooting.mdx` | Mirror troubleshooting additions. |

---

## Risks

| Risk | Mitigation |
|---|---|
| SQLite block breaks existing MySQL deploys | Driver defaults to `mysql`; block is only emitted/executed when `sqlite` is explicitly chosen. A test asserts the `mysql` workflow is unchanged. |
| `.env.example` lacks `DB_CONNECTION`/`DB_DATABASE` lines | `grep … || echo >>` appends them when missing instead of silently no-op'ing. |
| `~` not expanded by Laravel in `DB_DATABASE` | Use `$HOME`-expanded **absolute** path (quoted heredoc expands on the server), never a tilde. |
| FTP excludes the `.sqlite` file but not the `database/` dir | The block `mkdir -p`s `database/` and creates the symlink server-side; the file is never expected from the upload. |
| File permissions on shared hosting | `touch` creates the file as the SSH user (the same user PHP-FPM runs as on cPanel), so it is writable. Documented in troubleshooting. |
| Reusable Action can't template like the generated workflow | Action uses a runtime `if [ "${{ inputs.database }}" = "sqlite" ]` guard — equivalent behaviour without code generation. |

## Out of scope (per decisions taken)

- **No demo-reset / `migrate:fresh` on deploy** — data persists across deploys. (Could be a future opt-in `database.fresh_on_deploy` flag.)
- **No PostgreSQL / other drivers** — `select()` offers `mysql` / `sqlite` only; the structure leaves room to extend later.
- **No automatic backup of the SQLite file** before deploy — the file is untouched by deploys, so this is a separate concern.
