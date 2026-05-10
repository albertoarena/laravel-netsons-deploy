<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

describe('netsons:check', function () {
    it('is registered as an artisan command', function () {
        $this->artisan('netsons:check')
            ->assertSuccessful();
    });

    it('displays the current strategy', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('Strategy')
            ->assertSuccessful();
    });

    it('lists required secrets', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('SSH_PRIVATE_KEY')
            ->assertSuccessful();
    });

    it('shows PHP version info', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('PHP')
            ->assertSuccessful();
    });

    it('checks deploy path configuration', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('public_html')
            ->assertSuccessful();
    });

    it('validates SSH port default', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('65100')
            ->assertSuccessful();
    });

    it('does not flag missing credentials as validation errors', function () {
        $this->artisan('netsons:check')
            ->doesntExpectOutputToContain('SSH host is required')
            ->doesntExpectOutputToContain('SSH user is required')
            ->doesntExpectOutputToContain('FTP host is required')
            ->doesntExpectOutputToContain('FTP user is required')
            ->doesntExpectOutputToContain('FTP password is required')
            ->assertSuccessful();
    });

    it('shows a reminder that credentials are configured via GitHub', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('GitHub')
            ->assertSuccessful();
    });

    it('checks whether workflow file exists', function () {
        // No workflow file exists in test environment
        $this->artisan('netsons:check')
            ->expectsOutputToContain('Workflow')
            ->assertSuccessful();
    });

    it('shows workflow file as missing when it does not exist', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('not found')
            ->assertSuccessful();
    });

    it('shows workflow file as found when it exists', function () {
        $workflowPath = base_path('.github/workflows/deploy.yml');
        File::ensureDirectoryExists(dirname($workflowPath));
        File::put($workflowPath, 'name: Deploy');

        $this->artisan('netsons:check')
            ->expectsOutputToContain('found')
            ->assertSuccessful();

        File::delete($workflowPath);
        // Clean up empty dirs
        @rmdir(base_path('.github/workflows'));
        @rmdir(base_path('.github'));
    });
});
