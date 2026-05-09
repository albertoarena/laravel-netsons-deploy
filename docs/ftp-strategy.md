# FTP Strategy

The FTP strategy builds your application in the GitHub Actions runner and uploads it to the server via FTP.

## How It Works

1. **Build** — GitHub Actions installs Composer dependencies and builds Node assets
2. **Prepare** — A new release directory is created on the server via SSH, copying the current release as a base
3. **Upload** — [SamKirkland/FTP-Deploy-Action](https://github.com/SamKirkland/FTP-Deploy-Action) syncs files incrementally via FTP
4. **Post-deploy** — Symlinks are set up, migrations run, caches rebuilt
5. **Switch** — The `current` symlink is updated to point to the new release
6. **Cleanup** — Old releases beyond the keep count are removed

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
