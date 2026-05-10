<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->configPath = config_path('netsons-deploy.php');
    $this->workflowPath = base_path('.github/workflows/deploy.yml');

    // Clean up if exists
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
    if (File::exists($this->workflowPath)) {
        File::delete($this->workflowPath);
    }
});

afterEach(function () {
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
    if (File::exists($this->workflowPath)) {
        File::delete($this->workflowPath);
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
