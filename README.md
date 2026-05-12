# Laravel Netsons Deploy

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-website-blue)](https://albertoarena.github.io/laravel-netsons-deploy/)

Deploy Laravel applications to **Netsons shared hosting** (cPanel, SSD plans) via GitHub Actions, supporting both **FTP upload** and **SSH/git-clone** strategies.

## Features

- Two deployment strategies: **FTP** (incremental sync) and **Git** (server-side clone)
- Release-based deployments with timestamped directories
- Zero-downtime release switching via proxy `index.php`
- Shared `.env` and `storage/` across releases
- Automatic cache clearing and rebuilding (with dependency caching for faster builds)
- Database migrations on deploy
- Automatic `key:generate` and seeder support on first deploy
- Auto-detect env variables from `.env.example` (secret-backed and static, with editable values)
- Custom `.env` variable management (secret-backed and static values)
- Build environment variables (e.g., Vite config)
- Custom post-deploy artisan commands
- [Envaudit](https://albertoarena.github.io/envaudit/) `.env` validation (opt-in, default enabled)
- Slack deploy notifications (opt-in)
- `.htaccess` management for root and public directories
- Configurable FTP root path for different account setups
- Configurable release retention (prune old releases)
- SSH cleanup after every deploy
- Works with Netsons SSH (port 65100) and cPanel PHP (`ea-phpXX`)

## Quick Start

### 1. Install the package

```bash
composer require albertoarena/laravel-netsons-deploy --dev
```

### 2. Run the installer

```bash
php artisan netsons:install
```

This will:
- Guide you through strategy selection (FTP or Git)
- Publish the config file to `config/netsons-deploy.php`
- Generate `.github/workflows/deploy.yml` with your settings
- Show the required GitHub Secrets and Variables

### 3. Configure environment variables

```bash
php artisan netsons:env add
```

Add secret-backed variables (e.g., `DB_PASSWORD`), static values (e.g., `SESSION_DRIVER=database`), or build variables (e.g., `VITE_APP_NAME`).

### 4. Configure GitHub Secrets

Add the required secrets to your GitHub repository (Settings > Secrets and variables > Actions). See [GitHub Secrets Reference](docs/github-secrets.md).

### 5. Deploy

Trigger the workflow from GitHub Actions > Deploy to Netsons > Run workflow.

## Strategy Comparison

| Feature | FTP | Git |
|---|---|---|
| **How it works** | Builds locally, uploads via FTP | Clones repo on server via SSH |
| **Asset building** | In GitHub Actions runner | In GitHub Actions, uploaded via SCP |
| **Composer install** | In GitHub Actions runner | On server using Netsons PHP CLI |
| **Requires on server** | FTP access | Git + SSH access (SSD 30+ plans) |
| **Transfer method** | Incremental FTP sync | `git clone --depth 1` |
| **Speed** | Slower (full upload first time) | Faster (shallow clone) |
| **Best for** | Any Netsons plan | SSD 30+ plans with git |
| **Private repos** | Supported (uploaded from runner) | Supported via SSH agent forwarding |

> **Private repos with Git strategy:** The workflow uses SSH agent forwarding (`-A`) so the runner's SSH key is available on the server during `git clone`. Add your SSH key's public half as a [GitHub deploy key](docs/github-secrets.md#private-repository-setup-git-strategy) (read-only).

## Usage as a Reusable GitHub Action

You can also use this as a reusable action in your workflow:

```yaml
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      # ... build steps (PHP, Node, assets) ...

      - uses: albertoarena/laravel-netsons-deploy@v1
        with:
          strategy: 'git'
          environment: 'production'
          deploy-path: 'public_html'
          ssh-host: ${{ vars.SSH_HOST }}
          ssh-user: ${{ vars.SSH_USER }}
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
          ssh-known-hosts: ${{ secrets.SSH_KNOWN_HOSTS }}
          git-repo: ${{ vars.GIT_REPO }}
```

See [`action.yml`](action.yml) for all available inputs.

## Artisan Commands

### `netsons:install`

Interactive setup wizard. Publishes config and deploy workflow, shows required secrets/variables.

```bash
php artisan netsons:install
php artisan netsons:install --strategy=git
php artisan netsons:install --force   # Regenerate workflow with current settings
```

### `netsons:check`

Shows your local configuration, checks that the workflow file exists, and lists required GitHub Secrets/Variables.

```bash
php artisan netsons:check
```

### `netsons:env`

Manage custom environment variables for deployment without editing the workflow manually.

```bash
php artisan netsons:env              # List all configured variables
php artisan netsons:env add          # Add a new variable (interactive)
php artisan netsons:env remove       # Remove a variable (interactive)
```

Variable types:
- **Secret-backed** — values from GitHub Secrets (e.g., `DB_PASSWORD`)
- **Static** — fixed values (e.g., `SESSION_DRIVER=database`)
- **Build** — available during asset build (e.g., `VITE_APP_NAME`)

After adding/removing variables, regenerate the workflow:

```bash
php artisan netsons:install --force
```

## Configuration

The config file `config/netsons-deploy.php` covers:

- **Strategy** — `ftp` or `git`
- **SSH** — host, port (default 65100), user
- **PHP binary** — remote path (default `/usr/local/bin/ea-php84`)
- **Deploy path** — remote directory (default `public_html`)
- **FTP** — host, port, user, password, protocol, root path
- **Git** — repo URL, branch
- **Releases** — number to keep (default 5)
- **Post-deploy** — toggle migrations, cache rebuilding, queue restart
- **Seeders** — classes to run on first deploy

The `netsons-deploy.json` file manages:

- **Secret-backed env vars** — mapped to GitHub Secrets
- **Static env vars** — fixed values per deployment
- **Build env vars** — available during asset build
- **Custom commands** — extra artisan commands for post-deploy
- **Notifications** — Slack webhook for deploy alerts

See [Configuration Reference](docs/configuration.md) for details.

## Documentation

- [Configuration Reference](docs/configuration.md)
- [FTP Strategy Guide](docs/ftp-strategy.md)
- [Git Strategy Guide](docs/git-strategy.md)
- [Netsons Setup Guide](docs/netsons-setup.md)
- [GitHub Secrets Reference](docs/github-secrets.md)
- [Troubleshooting](docs/troubleshooting.md)

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- GitHub Actions
- Netsons shared hosting (cPanel, SSD plans)

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Commit your changes
6. Push to the branch
7. Open a pull request

## License

MIT License. See [LICENSE](LICENSE) for details.
