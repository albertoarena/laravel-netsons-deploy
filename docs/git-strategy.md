# Git Strategy

The Git strategy clones your repository directly on the server via SSH and installs dependencies there.

## How It Works

1. **Build** — GitHub Actions builds Node assets locally
2. **Clone** — The repository is cloned on the server via SSH (`git clone --depth 1`)
3. **Install** — Composer dependencies are installed on the server using the Netsons PHP binary
4. **Upload assets** — Built CSS/JS assets are uploaded to the server via SCP
5. **Post-deploy** — Symlinks are set up, migrations run, caches rebuilt
6. **Switch** — The `current` symlink is updated to point to the new release
7. **Cleanup** — Old releases beyond the keep count are removed

## When to Use

- Requires **SSD 30+ plan** (git must be available on server)
- Faster deployments (shallow clone instead of full FTP upload)
- Composer runs on server — ensure the server has enough resources

## Required Secrets

| Secret | Description |
|---|---|
| `SSH_PRIVATE_KEY` | SSH private key for server access |
| `SSH_KNOWN_HOSTS` | SSH known hosts entry |
| `SSH_KEY_PASSPHRASE` | SSH key passphrase (if set) |

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
| `GIT_REPO` | Git repository URL (SSH format) |
| `GIT_BRANCH` | Git branch to deploy |

## Server Requirements

- **Git** — available on SSD 30+ plans
- **Composer** — typically at `/opt/cpanel/composer/bin/composer`
- **PHP CLI** — use the full path (e.g., `/usr/local/bin/ea-php84`)
- **SSH access** — with key authentication

## SSH Key for Git Clone

The server needs SSH access to your GitHub repository. You can either:

1. **Deploy key** — add a read-only deploy key to the repository
2. **SSH key** — use the same SSH key configured for server access

For deploy keys, add the server's public key in GitHub > Repository > Settings > Deploy keys.

## Composer on Netsons

Composer must be invoked with the correct PHP binary:

```bash
/usr/local/bin/ea-php84 /opt/cpanel/composer/bin/composer install --no-dev
```

The action handles this automatically using the `remote-php` input.

## Server Directory Structure

Same as FTP strategy — see [FTP Strategy](ftp-strategy.md#server-directory-structure).
