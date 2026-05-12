# Netsons Setup Guide

How to configure your Netsons cPanel hosting for deployment.

## SSH Access

### 1. Generate an SSH Key Pair

On your local machine:

```bash
ssh-keygen -t ed25519 -C "deploy@your-domain.com"
```

This creates two files:
- `~/.ssh/id_ed25519` — private key (keep secret)
- `~/.ssh/id_ed25519.pub` — public key (upload to server)

### 2. Upload the Public Key to Netsons

1. Log in to your Netsons cPanel
2. Go to **Security** > **SSH Access**
3. Click **Manage SSH Keys**
4. Click **Import Key**
5. Paste the contents of your public key
6. Click **Import**
7. Go back to **Manage SSH Keys** and click **Manage** next to the key
8. Click **Authorize** to enable the key

### 3. Test SSH Connection

```bash
ssh -p 65100 your-user@your-server.netsons.com
```

> **Important:** Netsons uses port **65100** for SSH, not the standard port 22.

### 4. Get the Known Hosts Entry

```bash
ssh-keyscan -p 65100 your-server.netsons.com
```

Save the output — you'll need it for the `SSH_KNOWN_HOSTS` GitHub secret.

## FTP Access

FTP credentials are typically the same as your cPanel login:

- **Host:** your-server.netsons.com
- **Port:** 21
- **Username:** your cPanel username
- **Password:** your cPanel password

You can also create a separate FTP account in cPanel > Files > FTP Accounts.

## PHP Version

### Check Available PHP Versions

```bash
ls /usr/local/bin/ea-php*
```

Common versions:
- `/usr/local/bin/ea-php82`
- `/usr/local/bin/ea-php83`
- `/usr/local/bin/ea-php84`

### Check Current PHP Version

```bash
/usr/local/bin/ea-php84 -v
```

### Set PHP Version in cPanel

1. Go to **Software** > **MultiPHP Manager**
2. Select your domain
3. Choose the desired PHP version
4. Click **Apply**

> **Note:** The cPanel PHP version affects the web server. For CLI operations (artisan, composer), always use the full path like `/usr/local/bin/ea-php84`.

## Composer

Composer is typically available at:

```bash
/opt/cpanel/composer/bin/composer
```

Always invoke it with the correct PHP binary:

```bash
/usr/local/bin/ea-php84 /opt/cpanel/composer/bin/composer --version
```

## Directory Structure Preparation

Before your first deploy, no special preparation is needed. The deployment process will create the required directory structure automatically:

```
~/public_html/
├── releases/      # Created automatically
├── shared/        # Created automatically
├── current        # Symlink, created automatically
├── index.php      # Proxy, created automatically
└── .htaccess      # Created automatically
```

A `.first_deploy` flag file is created on the first deployment to trigger any configured seeders.

## Git (SSD 30+ Plans)

Check if git is available:

```bash
which git
git --version
```

Git is available on SSD 30+ plans. Lower plans do not have git and must use the FTP strategy.

### Private Repositories

For private repos, the SSH key used for Netsons access must also be registered as a deploy key on GitHub. The workflow handles SSH agent forwarding and GitHub host key setup automatically. See [Private Repository Setup](github-secrets.md#private-repository-setup-git-strategy) for details.
