<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->jsonPath = base_path('netsons-deploy.json');

    if (File::exists($this->jsonPath)) {
        File::delete($this->jsonPath);
    }
});

afterEach(function () {
    if (File::exists($this->jsonPath)) {
        File::delete($this->jsonPath);
    }
});

describe('netsons:env', function () {
    it('is registered as an artisan command', function () {
        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->assertSuccessful();
    });

    it('defaults to list action', function () {
        $this->artisan('netsons:env', ['--no-interaction' => true])
            ->expectsOutputToContain('Environment Configuration')
            ->assertSuccessful();
    });

    it('shows empty state when no json file exists', function () {
        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->expectsOutputToContain('No custom environment variables configured')
            ->assertSuccessful();
    });

    it('lists configured env mappings', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_DATABASE' => 'DB_DATABASE'],
        ]));

        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->expectsOutputToContain('DB_DATABASE')
            ->assertSuccessful();
    });

    it('lists configured static variables', function () {
        File::put($this->jsonPath, json_encode([
            'env_static' => ['SESSION_DRIVER' => 'database'],
        ]));

        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->expectsOutputToContain('SESSION_DRIVER')
            ->expectsOutputToContain('Static variables')
            ->assertSuccessful();
    });

    it('lists configured build env variables', function () {
        File::put($this->jsonPath, json_encode([
            'build_env' => ['VITE_APP_NAME' => 'My App'],
        ]));

        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->expectsOutputToContain('VITE_APP_NAME')
            ->expectsOutputToContain('Build variables')
            ->assertSuccessful();
    });

    it('lists configured custom commands', function () {
        File::put($this->jsonPath, json_encode([
            'custom_commands' => ['event-sourcing:cache-event-handlers 2>/dev/null || true'],
        ]));

        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->expectsOutputToContain('event-sourcing:cache-event-handlers')
            ->assertSuccessful();
    });

    it('lists configured slack webhook', function () {
        File::put($this->jsonPath, json_encode([
            'notifications' => ['slack_webhook_secret' => 'SLACK_WEBHOOK_DEBUG'],
        ]));

        $this->artisan('netsons:env', ['action' => 'list', '--no-interaction' => true])
            ->expectsOutputToContain('SLACK_WEBHOOK_DEBUG')
            ->assertSuccessful();
    });
});

describe('netsons:env add', function () {
    it('adds a secret-backed variable interactively', function () {
        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'Secret-backed (from GitHub Secrets)', [
                'Secret-backed (from GitHub Secrets)',
                'Static (fixed value)',
                'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'DB_PASSWORD')
            ->expectsQuestion('GitHub Secret name (default: same as ENV name)', 'DB_PASSWORD')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping'])->toBe(['DB_PASSWORD' => 'DB_PASSWORD']);
    });

    it('adds a static variable interactively', function () {
        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'Static (fixed value)', [
                'Secret-backed (from GitHub Secrets)',
                'Static (fixed value)',
                'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'SESSION_DRIVER')
            ->expectsQuestion('Value', 'database')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_static'])->toBe(['SESSION_DRIVER' => 'database']);
    });

    it('adds a build env variable interactively', function () {
        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'Build (available during asset build)', [
                'Secret-backed (from GitHub Secrets)',
                'Static (fixed value)',
                'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'VITE_APP_NAME')
            ->expectsQuestion('Value', 'My App')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['build_env'])->toBe(['VITE_APP_NAME' => 'My App']);
    });
});

describe('netsons:env remove', function () {
    it('removes a variable interactively', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_DATABASE' => 'DB_DATABASE', 'DB_USERNAME' => 'DB_USERNAME'],
        ]));

        $this->artisan('netsons:env', ['action' => 'remove'])
            ->expectsChoice('Which variable to remove?', 'DB_DATABASE (secret: DB_DATABASE)', [
                'DB_DATABASE (secret: DB_DATABASE)',
                'DB_USERNAME (secret: DB_USERNAME)',
            ])
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping'])->toBe(['DB_USERNAME' => 'DB_USERNAME']);
    });

    it('shows message when nothing to remove', function () {
        $this->artisan('netsons:env', ['action' => 'remove', '--no-interaction' => true])
            ->expectsOutputToContain('No variables configured')
            ->assertSuccessful();
    });
});
