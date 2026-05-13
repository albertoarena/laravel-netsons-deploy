<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->configPath = config_path('netsons-deploy.php');
    $this->workflowPath = base_path('.github/workflows/deploy.yml');
    $this->jsonPath = base_path('netsons-deploy.json');

    // Clean up if exists
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
    if (File::exists($this->workflowPath)) {
        File::delete($this->workflowPath);
    }
    if (File::exists($this->jsonPath)) {
        File::delete($this->jsonPath);
    }
});

afterEach(function () {
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
    if (File::exists($this->workflowPath)) {
        File::delete($this->workflowPath);
    }
    if (File::exists($this->jsonPath)) {
        File::delete($this->jsonPath);
    }
    // Clean up empty dirs
    @rmdir(base_path('.github/workflows'));
    @rmdir(base_path('.github'));
});

describe('netsons:install', function () {
    it('is registered as an artisan command', function () {
        $this->artisan('netsons:install', ['--no-interaction' => true])
            ->assertSuccessful();
    });

    it('publishes the config file', function () {
        $this->artisan('netsons:install', ['--no-interaction' => true])
            ->assertSuccessful();

        expect(File::exists($this->configPath))->toBeTrue();
    });

    it('displays strategy selection prompt info', function () {
        $this->artisan('netsons:install', ['--no-interaction' => true])
            ->expectsOutputToContain('Netsons Deploy')
            ->assertSuccessful();
    });

    it('accepts the ftp strategy option', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();
    });

    it('accepts the git strategy option', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();
    });

    it('shows required secrets for ftp strategy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->expectsOutputToContain('FTP_HOST')
            ->assertSuccessful();
    });

    it('shows required secrets for git strategy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->expectsOutputToContain('SSH_PRIVATE_KEY')
            ->assertSuccessful();
    });

    it('writes the selected strategy into the published config', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->configPath);
        expect($contents)->toContain("'NETSONS_DEPLOY_STRATEGY', 'git'");
    });

    it('writes ftp strategy into config when ftp is selected', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->configPath);
        expect($contents)->toContain("'NETSONS_DEPLOY_STRATEGY', 'ftp'");
    });

    it('warns when config already exists on re-run', function () {
        // First install
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        // Second install — should mention existing config
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true, '--force' => true])
            ->expectsOutputToContain('existing')
            ->assertSuccessful();
    });

    it('updates strategy in existing config with --force', function () {
        // Install with ftp
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->configPath);
        expect($contents)->toContain("'NETSONS_DEPLOY_STRATEGY', 'ftp'");

        // Re-install with git and --force
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->configPath);
        expect($contents)->toContain("'NETSONS_DEPLOY_STRATEGY', 'git'");
    });

    it('skips overwrite without --force when config exists in non-interactive mode', function () {
        // First install
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        // Second install without --force — should keep existing config
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->configPath);
        expect($contents)->toContain("'NETSONS_DEPLOY_STRATEGY', 'ftp'");
    });

    it('publishes the deploy workflow file', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        expect(File::exists($this->workflowPath))->toBeTrue();
    });

    it('replaces strategy placeholder in published workflow', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain("STRATEGY: 'git'");
        expect($contents)->not->toContain('%%STRATEGY%%');
    });

    it('replaces php version placeholder in published workflow', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toContain('%%PHP_VERSION%%');
    });

    it('replaces all placeholders in published workflow', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toContain('%%');
    });

    it('does not overwrite existing workflow without --force', function () {
        // Create a custom workflow
        File::ensureDirectoryExists(dirname($this->workflowPath));
        File::put($this->workflowPath, 'custom workflow content');

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toBe('custom workflow content');
    });

    it('overwrites existing workflow with --force', function () {
        // Create a custom workflow
        File::ensureDirectoryExists(dirname($this->workflowPath));
        File::put($this->workflowPath, 'custom workflow content');

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toBe('custom workflow content');
        expect($contents)->toContain('Deploy to Netsons');
    });

    it('shows workflow published message', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->expectsOutputToContain('.github/workflows/deploy.yml')
            ->assertSuccessful();
    });
});

describe('netsons:install workflow features', function () {
    // B8 fix: Reconfigure preserves existing values in generated workflow
    it('preserves existing env_static values when reconfiguring', function () {
        // Simulate existing config with user-customized values
        File::put($this->jsonPath, json_encode([
            'env_static' => ['SESSION_DRIVER' => 'database', 'LARAVEL_PDF_DRIVER' => 'dompdf'],
            'build_env' => ['VITE_APP_NAME' => 'My Custom App'],
            'custom_commands' => ['permission:cache-reset'],
            'envaudit' => true,
        ]));

        // Non-interactive regeneration should use existing JSON as-is
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('SESSION_DRIVER=\\"database\\"');
        expect($contents)->toContain('LARAVEL_PDF_DRIVER=\\"dompdf\\"');
        expect($contents)->toContain('VITE_APP_NAME');
        expect($contents)->toContain('My Custom App');
        expect($contents)->toContain('permission:cache-reset');
        expect($contents)->toContain('envaudit');
    });

    // B15: SSH agent env export
    it('exports SSH agent env vars to GITHUB_ENV', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('SSH_AUTH_SOCK=$SSH_AUTH_SOCK');
        expect($contents)->toContain('SSH_AGENT_PID=$SSH_AGENT_PID');
        expect($contents)->toContain('GITHUB_ENV');
    });

    // B14: SSH values as secrets not vars
    it('uses secrets for SSH_HOST SSH_USER SSH_PORT in generated workflow', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('secrets.SSH_HOST');
        expect($contents)->toContain('secrets.SSH_USER');
        expect($contents)->toContain('secrets.SSH_PORT');
        expect($contents)->not->toContain('vars.SSH_HOST');
        expect($contents)->not->toContain('vars.SSH_USER');
        expect($contents)->not->toContain('vars.SSH_PORT');
    });

    // B13: SSH askpass fix
    it('uses askpass script for SSH passphrase handling', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('/tmp/askpass.sh');
        expect($contents)->toContain('SSH_ASKPASS=/tmp/askpass.sh');
        expect($contents)->toContain('SSH_ASKPASS_REQUIRE=force');
        expect($contents)->not->toContain('echo "${SSH_KEY_PASSPHRASE}" | SSH_ASKPASS_REQUIRE=force');
    });

    // B12: Prepare Laravel directories
    it('includes prepare Laravel directories step', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Prepare Laravel directories');
        expect($contents)->toContain('mkdir -p bootstrap/cache');
        expect($contents)->toContain('mkdir -p storage/framework/{sessions,views,cache}');
    });

    // W1: Dependency caching
    it('includes Composer cache steps in generated workflow', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Get Composer cache directory');
        expect($contents)->toContain('Cache Composer dependencies');
        expect($contents)->toContain('actions/cache@v4');
        expect($contents)->toContain("hashFiles('**/composer.lock')");
    });

    it('includes Node cache in setup-node step', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('cache: ${{ env.PACKAGE_MANAGER }}');
    });

    // W3: key:generate on first deploy
    it('includes key:generate step for first deploy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Generate app key on first deploy');
        expect($contents)->toContain('artisan key:generate --force');
        expect($contents)->toContain('.first_deploy');
    });

    // W4: Seeders + .first_deploy cleanup
    it('includes first-deploy seeders step', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Run seeders on first deploy');
        expect($contents)->toContain('rm ~/${{ vars.DEPLOY_PATH }}/.first_deploy');
    });

    // W5: SSH cleanup
    it('includes SSH cleanup step with if always', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Cleanup SSH');
        expect($contents)->toContain('if: always()');
        expect($contents)->toContain('rm -f $HOME/.ssh/deploy_key');
        expect($contents)->toContain('SSH_AGENT_PID');
    });

    // W6: package:discover always present
    it('includes package:discover in cache rebuild', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('artisan package:discover --ansi');
    });

    // W2: env_mapping from netsons-deploy.json
    it('includes env_mapping sed commands when netsons-deploy.json has mappings', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_PASSWORD' => 'DB_PASSWORD'],
            'env_static' => ['SESSION_DRIVER' => 'database'],
        ]));

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('secrets.DB_PASSWORD');
        expect($contents)->toContain('SESSION_DRIVER');
        expect($contents)->toContain('database');
    });

    it('generates clean workflow without env mappings when json is empty', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toContain('%%ENV_MAPPING');
        expect($contents)->toContain('Update .env values');
    });

    // W7: Build env vars
    it('includes build env vars when netsons-deploy.json has build_env', function () {
        File::put($this->jsonPath, json_encode([
            'build_env' => ['VITE_APP_NAME' => 'My App'],
        ]));

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('VITE_APP_NAME: "My App"');
    });

    it('generates build step without env block when no build_env configured', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Build assets');
        expect($contents)->not->toContain('VITE_');
    });

    // W6: Custom commands
    it('includes custom commands when netsons-deploy.json has them', function () {
        File::put($this->jsonPath, json_encode([
            'custom_commands' => ['event-sourcing:cache-event-handlers 2>/dev/null || true'],
        ]));

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('event-sourcing:cache-event-handlers');
    });

    // W8: Slack notifications
    it('includes Slack notification steps when configured', function () {
        File::put($this->jsonPath, json_encode([
            'notifications' => ['slack_webhook_secret' => 'SLACK_WEBHOOK_DEBUG'],
        ]));

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Notify Slack on success');
        expect($contents)->toContain('Notify Slack on failure');
        expect($contents)->toContain('secrets.SLACK_WEBHOOK_DEBUG');
    });

    it('does not include Slack steps when not configured', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toContain('Notify Slack');
    });

    // W9: FTP server-dir
    it('uses deploy path in FTP server-dir by default', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('server-dir: ${{ vars.DEPLOY_PATH }}/releases/');
    });

    // Interactive env setup: skipped in non-interactive mode
    it('does not create netsons-deploy.json in non-interactive mode', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        expect(File::exists($this->jsonPath))->toBeFalse();
    });

    // B11: Edited static values appear in generated workflow
    it('uses edited static values in generated workflow', function () {
        // Simulate a user who selected SESSION_DRIVER but changed value to database
        File::put($this->jsonPath, json_encode([
            'env_static' => ['SESSION_DRIVER' => 'database', 'LARAVEL_PDF_DRIVER' => 'dompdf'],
        ]));

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('SESSION_DRIVER');
        expect($contents)->toContain('database');
        expect($contents)->toContain('LARAVEL_PDF_DRIVER');
        expect($contents)->toContain('dompdf');
    });

    // B6: Envaudit
    it('includes envaudit step when configured', function () {
        File::put($this->jsonPath, json_encode([
            'envaudit' => true,
        ]));

        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true, '--force' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Validate .env with envaudit');
        expect($contents)->toContain('npx @albertoarena/envaudit check --ci --no-color');
    });

    it('does not include envaudit step when not configured', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toContain('envaudit');
    });

    it('disables persist-credentials on checkout to prevent token leaking into Composer', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('persist-credentials: false');
    });

    // HTTPS cloning for git strategy (Netsons blocks outbound SSH)
    it('does not use SSH agent forwarding in git deploy step', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->not->toContain('ssh -A');
        expect($contents)->not->toContain('ssh-keyscan github.com');
    });

    it('has a Prepare clone URL step that writes to GITHUB_ENV', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('Prepare clone URL');
        expect($contents)->toContain('GITHUB_ENV');
        expect($contents)->toContain('x-access-token');
        expect($contents)->toContain('secrets.GIT_TOKEN');
    });

    it('does not pass GIT_REPO through env block to avoid slash escaping', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);

        // GIT_REPO must NOT be in the env: block (GitHub Actions escapes slashes there)
        expect($contents)->not->toMatch('/env:\s*\n\s+GIT_REPO:/');

        // vars.GIT_REPO must be used in run: script with backslash stripping
        expect($contents)->toContain("tr -d '\\\\'");
        expect($contents)->toContain('vars.GIT_REPO');
    });

    it('uses CLONE_URL env var in git deploy step', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('${CLONE_URL}');
    });

    // G1: Git strategy skips PHP/Composer on runner
    it('skips runner PHP and Composer steps for git strategy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);

        // These steps should be conditional on FTP
        expect($contents)->toMatch('/- name: Prepare Laravel directories\s+if: env\.STRATEGY == \'ftp\'/');
        expect($contents)->toMatch('/- name: Setup PHP\s+if: env\.STRATEGY == \'ftp\'/');
        expect($contents)->toMatch('/- name: Get Composer cache directory\s+if: env\.STRATEGY == \'ftp\'/');
        expect($contents)->toMatch('/- name: Cache Composer dependencies\s+if: env\.STRATEGY == \'ftp\'/');
        expect($contents)->toMatch('/- name: Install Composer dependencies\s+if: env\.STRATEGY == \'ftp\'/');
    });

    it('does not skip Node setup and build for git strategy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);

        // Node steps must NOT have FTP-only conditionals
        expect($contents)->not->toMatch('/- name: Setup Node\.js\s+if:/');
        expect($contents)->not->toMatch('/- name: Install Node dependencies\s+if:/');
        expect($contents)->not->toMatch('/- name: Build assets\s+if:/');
    });

    // G2: Git strategy skips copy-current release, uses simpler mkdir
    it('skips copy-current release step for git strategy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'git', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);

        // The copy-current step should be FTP-only
        expect($contents)->toMatch('/- name: Create release directory\s+if: env\.STRATEGY == \'ftp\'/');

        // Git should have a simpler step that just ensures releases/ exists
        expect($contents)->toContain('Ensure releases directory');
    });

    it('keeps all runner build steps unconditional for ftp strategy', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);

        // All build steps should still be present
        expect($contents)->toContain('Prepare Laravel directories');
        expect($contents)->toContain('Setup PHP');
        expect($contents)->toContain('Install Composer dependencies');
        expect($contents)->toContain('Setup Node.js');
        expect($contents)->toContain('Build assets');
        expect($contents)->toContain('Create release directory');
    });

    // B20: Root .htaccess uses RewriteCond to prevent loop
    it('generates root htaccess with RewriteCond to prevent rewrite loop', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('RewriteCond %{REQUEST_URI} !^/public/');
    });

    // B21: Copy public .htaccess during release activation
    it('copies public htaccess from release during activation', function () {
        $this->artisan('netsons:install', ['--strategy' => 'ftp', '--no-interaction' => true])
            ->assertSuccessful();

        $contents = File::get($this->workflowPath);
        expect($contents)->toContain('public/.htaccess');
        expect($contents)->toContain('DEPLOY_BASE}/public/.htaccess');
    });
});
