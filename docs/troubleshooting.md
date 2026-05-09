# Troubleshooting

Common issues and solutions when deploying to Netsons.

## SSH Connection

### "Connection refused" or "Connection timed out"

Netsons uses port **65100** for SSH, not port 22.

```bash
# Wrong
ssh user@server.netsons.com

# Correct
ssh -p 65100 user@server.netsons.com
```

Ensure your `ssh-port` input or `SSH_PORT` variable is set to `65100`.

### "Host key verification failed"

Your `SSH_KNOWN_HOSTS` secret may be missing or incorrect. Regenerate it:

```bash
ssh-keyscan -p 65100 your-server.netsons.com
```

Copy the full output to the `SSH_KNOWN_HOSTS` secret.

### "Permission denied (publickey)"

1. Verify the public key is **authorized** in cPanel > Security > SSH Access
2. Check the private key in `SSH_PRIVATE_KEY` includes the full content (from `-----BEGIN` to `-----END`)
3. If using a passphrase, ensure `SSH_KEY_PASSPHRASE` is set correctly

## PHP Version Mismatch

### "php: command not found" or wrong PHP version

The default `php` command on Netsons may point to an old version. Always use the full path:

```bash
/usr/local/bin/ea-php84 -v
```

Ensure the `remote-php` input matches the PHP version you need:

```yaml
remote-php: '/usr/local/bin/ea-php84'
```

### Check available versions

```bash
ssh -p 65100 user@server.netsons.com "ls /usr/local/bin/ea-php*"
```

## Composer Issues

### "composer: command not found"

Use the full path with the correct PHP binary:

```bash
/usr/local/bin/ea-php84 /opt/cpanel/composer/bin/composer install
```

### "Allowed memory size exhausted"

Increase memory limit for the Composer command:

```bash
/usr/local/bin/ea-php84 -d memory_limit=-1 /opt/cpanel/composer/bin/composer install
```

## FTP Issues

### "Login authentication failed"

Verify your FTP credentials in cPanel > Files > FTP Accounts. The FTP username is usually your cPanel username.

### "Timeout" during upload

Large first-time uploads may time out. The FTP strategy copies the previous release first, so subsequent deploys only transfer changed files.

### Files not updating

The FTP action uses incremental sync. If files seem stale, check:
- The `server-dir` path is correct
- The FTP user has write permissions to the target directory

## Deployment Issues

### "artisan: command not found"

The deployment uses SSH to run artisan commands. Ensure:
1. The release directory exists and contains the Laravel application
2. The `.env` symlink points to the shared `.env` file
3. The PHP binary path is correct

### Migrations fail

- Check the shared `.env` has correct database credentials
- Verify the database exists and is accessible from the server
- Test manually: `ssh -p 65100 user@server "cd ~/public_html/current && /usr/local/bin/ea-php84 artisan migrate:status"`

### Storage permissions

If you see "Permission denied" errors related to storage:

```bash
ssh -p 65100 user@server "chmod -R 775 ~/public_html/shared/storage"
```

### ".env not found" or "APP_KEY not set"

The shared `.env` is created from `.env.example` on first deploy. You need to:
1. SSH into the server
2. Edit `~/public_html/shared/.env`
3. Set `APP_KEY`, database credentials, and other required values

Generate an app key:
```bash
ssh -p 65100 user@server "cd ~/public_html/current && /usr/local/bin/ea-php84 artisan key:generate"
```

## .htaccess Issues

### "500 Internal Server Error"

Check if `mod_rewrite` is enabled. In most Netsons plans it is enabled by default. Verify the `.htaccess` syntax is correct.

### Assets not loading (CSS/JS 404)

Ensure the proxy `index.php` and `.htaccess` are properly set up. The root `.htaccess` should rewrite to `public/`, and assets should be accessible at `public/build/`.

## Release Management

### "No space left on device"

Reduce the number of kept releases:

```yaml
releases-keep: '3'
```

Or manually clean up old releases:

```bash
ssh -p 65100 user@server "ls ~/public_html/releases/"
ssh -p 65100 user@server "rm -rf ~/public_html/releases/20240101120000"
```

### Rolling back to a previous release

To roll back, update the `current` symlink:

```bash
ssh -p 65100 user@server "ln -sfn ~/public_html/releases/PREVIOUS_TIMESTAMP ~/public_html/current"
```
