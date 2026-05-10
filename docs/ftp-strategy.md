# FTP Strategy

The FTP strategy builds your application in the GitHub Actions runner and uploads it to the server via FTP.

## How It Works

1. **Build** — GitHub Actions prepares Laravel directories, installs Composer dependencies, and builds Node assets (with dependency caching for faster builds)
2. **Prepare** — A new release directory is created on the server via SSH, copying the current release as a base
3. **Upload** — [SamKirkland/FTP-Deploy-Action](https://github.com/SamKirkland/FTP-Deploy-Action) syncs files incrementally via FTP
4. **First deploy** — If this is the first deploy, generates `APP_KEY` and runs configured seeders
5. **Post-deploy** — Symlinks are set up, `.env` values updated, migrations run, caches rebuilt
6. **Switch** — The `current` symlink is updated to point to the new release
7. **Cleanup** — Old releases beyond the keep count are removed, SSH agent cleaned up

## When to Use

- Works on **all Netsons plans** (no git required on server)
- Good for projects where you want full control over what gets uploaded
- First deploy is slower (full upload), subsequent deploys are incremental

## Required Secrets

| Secret | Description |
|---|---|
| `SSH_PRIVATE_KEY` | SSH private key for server access |
| `SSH_KNOWN_HOSTS` | SSH known hosts entry |
| `SSH_KEY_PASSPHRASE` | SSH key passphrase (if set) |
| `FTP_HOST` | FTP server hostname |
| `FTP_USER` | FTP username |
| `FTP_PASS` | FTP password |
| `FTP_PORT` | FTP port (default: 21) |

## Required Variables

| Variable | Description |
|---|---|
| `SSH_HOST` | SSH hostname |
| `SSH_PORT` | SSH port (default: 65100) |
| `SSH_USER` | SSH username |
| `DEPLOY_PATH` | Remote deploy path (e.g., `public_html`) |
| `APP_ENV` | Application environment |
| `APP_DEBUG` | Debug mode |
| `APP_URL` | Application URL |

## FTP Root Path

The FTP account root directory affects how files are uploaded. There are two common setups:

### Home directory root (default)

FTP root is `/home/user/`. The workflow uses `DEPLOY_PATH/releases/...` as the server directory. This is the default and works when your FTP account has access to the full home directory.

### Site-scoped root

FTP root is `/home/user/mysite.com/`. The workflow uses `releases/...` directly, since the deploy path is already part of the FTP root.

Set this in `config/netsons-deploy.php`:

```php
'ftp' => [
    // ...
    'root_path' => env('NETSONS_FTP_ROOT_PATH', '/home/user/mysite.com'),
],
```

To check or change your FTP root, go to cPanel > Files > FTP Accounts > Configure FTP Client.

## Server Directory Structure

After deployment, your server will look like:

```
~/public_html/
├── .htaccess              # Root rewrite to public/
├── index.php              # Proxy to active release
├── current -> releases/20240101120000/  # Symlink
├── shared/
│   ├── .env               # Shared environment file
│   └── storage/           # Shared storage directory
└── releases/
    ├── 20240101120000/    # Current release
    ├── 20231215100000/    # Previous release
    └── ...
```

## FTP Exclusions

The following paths are excluded from FTP upload:

- `.git*` — Git files and directories
- `node_modules/` — Node dependencies
- `tests/` — Test files
- `.env`, `.env.*` — Environment files
- `storage/logs/` — Log files
