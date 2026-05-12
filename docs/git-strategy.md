# Git Strategy

The Git strategy clones your repository directly on the server via SSH and installs dependencies there.

## How It Works

1. **Build** — GitHub Actions prepares Laravel directories, builds Node assets locally (with dependency caching for faster builds)
2. **Clone** — The repository is cloned on the server via SSH (`git clone --depth 1`)
3. **Install** — Composer dependencies are installed on the server using the Netsons PHP binary
4. **Upload assets** — Built CSS/JS assets are uploaded to the server via SCP
5. **First deploy** — If first deploy, generates `APP_KEY` and runs configured seeders
6. **Post-deploy** — Symlinks are set up, `.env` values updated, migrations run, caches rebuilt
7. **Switch** — The `current` symlink is updated to point to the new release
8. **Cleanup** — Old releases beyond the keep count are removed, SSH agent cleaned up

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

## Private Repositories

The workflow uses **SSH agent forwarding** (`-A`) so the runner's SSH key is available on the Netsons server during `git clone`. This means private repositories work out of the box, provided the SSH key has read access to the GitHub repository.

### Setup

1. Use the same SSH key pair you generated for Netsons SSH access
2. Go to your GitHub repo > **Settings** > **Deploy keys**
3. Click **Add deploy key**
4. Paste the **public key** (e.g., `~/.ssh/id_ed25519.pub`)
5. Leave "Allow write access" unchecked (read-only is sufficient)
6. Click **Add key**

The workflow also automatically adds GitHub's host keys to the server's `known_hosts` before cloning, so no manual SSH configuration is needed on Netsons.

> **Note:** The same SSH private key authenticates to both Netsons (for SSH access) and GitHub (for git clone via agent forwarding). See [GitHub Secrets](github-secrets.md#private-repository-setup-git-strategy) for details.

## Composer on Netsons

Composer must be invoked with the correct PHP binary:

```bash
/usr/local/bin/ea-php84 /opt/cpanel/composer/bin/composer install --no-dev
```

The action handles this automatically using the `remote-php` input.

## Server Directory Structure

Same as FTP strategy — see [FTP Strategy](ftp-strategy.md#server-directory-structure).
