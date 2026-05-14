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

    it('shows netsons-deploy.json as not found when missing', function () {
        $jsonPath = base_path('netsons-deploy.json');
        if (File::exists($jsonPath)) {
            File::delete($jsonPath);
        }

        $this->artisan('netsons:check')
            ->expectsOutputToContain('netsons-deploy.json')
            ->expectsOutputToContain('not found')
            ->assertSuccessful();
    });

    it('displays SSH retries setting', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('SSH Retries')
            ->assertSuccessful();
    });

    it('displays SSH retry delay setting', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('SSH Retry Delay')
            ->assertSuccessful();
    });

    it('displays SSH connect timeout setting', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('SSH Connect Timeout')
            ->assertSuccessful();
    });

    it('shows netsons-deploy.json status when file exists', function () {
        $jsonPath = base_path('netsons-deploy.json');
        File::put($jsonPath, json_encode([
            'env_mapping' => ['DB_PASSWORD' => 'DB_PASSWORD'],
            'env_static' => ['SESSION_DRIVER' => 'database'],
            'custom_commands' => ['permission:cache-reset'],
        ]));

        $this->artisan('netsons:check')
            ->expectsOutputToContain('netsons-deploy.json')
            ->expectsOutputToContain('DB_PASSWORD')
            ->assertSuccessful();

        File::delete($jsonPath);
    });

    it('displays configured seeders from netsons-deploy.json', function () {
        $jsonPath = base_path('netsons-deploy.json');
        File::put($jsonPath, json_encode([
            'seeders' => ['RoleSeeder', 'PermissionSeeder'],
        ]));

        $this->artisan('netsons:check')
            ->expectsOutputToContain('RoleSeeder')
            ->assertSuccessful();

        File::delete($jsonPath);
    });

    it('displays multiple seeders from netsons-deploy.json', function () {
        $jsonPath = base_path('netsons-deploy.json');
        File::put($jsonPath, json_encode([
            'seeders' => ['RoleSeeder', 'PermissionSeeder'],
        ]));

        $this->artisan('netsons:check')
            ->expectsOutputToContain('RoleSeeder')
            ->doesntExpectOutputToContain('No seeders configured')
            ->assertSuccessful();

        File::delete($jsonPath);
    });

    it('shows no seeders configured when seeders list is empty', function () {
        $jsonPath = base_path('netsons-deploy.json');
        File::put($jsonPath, json_encode([
            'seeders' => [],
        ]));

        $this->artisan('netsons:check')
            ->expectsOutputToContain('No seeders configured')
            ->assertSuccessful();

        File::delete($jsonPath);
    });
});
