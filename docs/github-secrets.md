# GitHub Secrets & Variables

Configure these in your GitHub repository: **Settings** > **Secrets and variables** > **Actions**.

## Secrets

Secrets are encrypted and not visible after creation.

### Required (Both Strategies)

| Secret | Description | Example |
|---|---|---|
| `SSH_HOST` | SSH hostname | `your-server.netsons.com` |
| `SSH_USER` | SSH username | `your-cpanel-user` |
| `SSH_PRIVATE_KEY` | Full SSH private key content | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `SSH_KNOWN_HOSTS` | Known hosts entry for the server | Output of `ssh-keyscan -p 65100 host` |

### Optional (Both Strategies)

| Secret | Description | Example |
|---|---|---|
| `SSH_PORT` | SSH port (default: 65100) | `65100` |
| `SSH_KEY_PASSPHRASE` | SSH key passphrase, if set | `my-passphrase` |

### Required (FTP Strategy Only)

| Secret | Description | Example |
|---|---|---|
| `FTP_HOST` | FTP server hostname | `your-server.netsons.com` |
| `FTP_USER` | FTP username | `your-cpanel-user` |
| `FTP_PASS` | FTP password | `your-cpanel-password` |
| `FTP_PORT` | FTP port | `21` |

### Custom Env Variables

Any secret-backed env variables configured in `netsons-deploy.json` must also be added as secrets. For example:

| Secret | Description |
|---|---|
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |

Use `php artisan netsons:env list` to see all configured secrets, or `php artisan netsons:env add` to add new ones.

### Notifications (Optional)

| Secret | Description |
|---|---|
| `SLACK_WEBHOOK_DEBUG` | Slack webhook URL for deploy notifications |

The secret name is configurable via `netsons-deploy.json` notifications settings.

## Variables

Variables are visible in plain text. Do not store sensitive data here.

### Required (Both Strategies)

| Variable | Description | Example |
|---|---|---|
| `DEPLOY_PATH` | Deploy path relative to home | `public_html` |
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Debug mode | `false` |
| `APP_URL` | Application URL | `https://your-domain.com` |

### Required (Git Strategy Only)

| Variable | Description | Example |
|---|---|---|
| `GIT_REPO` | Git repository URL (SSH format) | `git@github.com:user/repo.git` |
| `GIT_BRANCH` | Branch to deploy | `main` |

## Environment-Specific Configuration

You can use GitHub Environments to have different values per environment (e.g., stage vs production):

1. Go to **Settings** > **Environments**
2. Create `stage` and `production` environments
3. Add environment-specific variables/secrets to each

This allows the same workflow to deploy to different servers or paths depending on the selected environment.

## Getting SSH Values

### SSH_PRIVATE_KEY

Copy the full content of your private key:

```bash
cat ~/.ssh/id_ed25519
```

Include everything from `-----BEGIN` to `-----END`.

### SSH_KNOWN_HOSTS

Scan the server:

```bash
ssh-keyscan -p 65100 your-server.netsons.com
```

Copy the full output.
