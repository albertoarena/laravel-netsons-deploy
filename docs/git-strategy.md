# Git Strategy

The Git strategy clones your repository directly on the server via SSH and installs dependencies there.

## How It Works

1. **Build assets** — GitHub Actions builds Node/CSS/JS assets locally (Netsons doesn't have Node)
2. **Clone** — The repository is cloned on the server via SSH (`git clone --depth 1`)
3. **Install** — Composer dependencies are installed on the server using the Netsons PHP binary
4. **Upload assets** — Built CSS/JS assets are uploaded from the runner to the server via SCP
5. **First deploy** — If first deploy, generates `APP_KEY` and runs configured seeders
6. **Post-deploy** — Symlinks are set up, `.env` values updated, migrations run, caches rebuilt
7. **Switch** — The `current` symlink is updated to point to the new release
8. **Cleanup** — Old releases beyond the keep count are removed, SSH agent cleaned up

Unlike the FTP strategy, the git strategy **does not** run PHP setup or Composer on the GitHub Actions runner — those only run on the Netsons server. The runner's only build responsibility is Node assets, which are uploaded via SCP after the server-side clone.

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
| `GIT_REPO` | Git repository HTTPS URL |
| `GIT_BRANCH` | Git branch to deploy |

## Server Requirements

- **Git** — available on SSD 30+ plans
- **Composer** — typically at `/opt/cpanel/composer/bin/composer`
- **PHP CLI** — use the full path (e.g., `/usr/local/bin/ea-php84`)
- **SSH access** — with key authentication

## Public Repositories

For public repos, set `GIT_REPO` to the HTTPS URL:

```
https://github.com/user/repo.git
```

No additional configuration is needed.

## Private Repositories

For private repos, a GitHub token is needed so the Netsons server can authenticate when cloning. Netsons shared hosting blocks outbound SSH (port 22), so HTTPS with token authentication is used instead.

### Using GITHUB_TOKEN (recommended for same-repo deploys)

Edit your `.github/workflows/deploy.yml` and change the `GIT_TOKEN` line in the "Deploy via Git" step:

```yaml
GIT_TOKEN: ${{ github.token }}
```

`github.token` is automatically provided by GitHub Actions with read access to the repository. No secrets to create or rotate.

### Using a Personal Access Token (cross-repo or fine-grained control)

1. Create a fine-grained PAT at github.com > Settings > Developer settings > Personal access tokens > Fine-grained tokens
2. Grant **read-only** access to the repository contents
3. Add it as a secret named `GIT_TOKEN` in your repo (Settings > Secrets)
4. The workflow already references `${{ secrets.GIT_TOKEN }}`

See [GitHub Secrets](github-secrets.md#private-repository-setup-git-strategy) for details.

## Composer on Netsons

Composer must be invoked with the correct PHP binary:

```bash
/usr/local/bin/ea-php84 /opt/cpanel/composer/bin/composer install --no-dev
```

The action handles this automatically using the `remote-php` input.

## Server Directory Structure

Same as FTP strategy — see [FTP Strategy](ftp-strategy.md#server-directory-structure).
