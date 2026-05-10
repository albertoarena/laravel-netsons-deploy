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
            ->expectsChoice('What type of variable?', 'secret', [
                'secret' => 'Secret-backed (from GitHub Secrets)',
                'static' => 'Static (fixed value)',
                'build' => 'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'DB_PASSWORD')
            ->expectsQuestion('GitHub Secret name (default: same as ENV name)', 'DB_PASSWORD')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping'])->toBe(['DB_PASSWORD' => 'DB_PASSWORD']);
    });

    it('adds a static variable interactively', function () {
        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'static', [
                'secret' => 'Secret-backed (from GitHub Secrets)',
                'static' => 'Static (fixed value)',
                'build' => 'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'SESSION_DRIVER')
            ->expectsQuestion('Value', 'database')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_static'])->toBe(['SESSION_DRIVER' => 'database']);
    });

    it('adds a build env variable interactively', function () {
        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'build', [
                'secret' => 'Secret-backed (from GitHub Secrets)',
                'static' => 'Static (fixed value)',
                'build' => 'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'VITE_APP_NAME')
            ->expectsQuestion('Value', 'My App')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['build_env'])->toBe(['VITE_APP_NAME' => 'My App']);
    });
});

describe('netsons:env add duplicate detection', function () {
    it('warns and offers update when secret-backed key already exists', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_PASSWORD' => 'OLD_SECRET'],
        ]));

        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'secret', [
                'secret' => 'Secret-backed (from GitHub Secrets)',
                'static' => 'Static (fixed value)',
                'build' => 'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'DB_PASSWORD')
            ->expectsOutputToContain('already configured')
            ->expectsConfirmation('"DB_PASSWORD" is already configured. Update it?', 'yes')
            ->expectsQuestion('GitHub Secret name (default: same as ENV name)', 'NEW_SECRET')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping']['DB_PASSWORD'])->toBe('NEW_SECRET');
    });

    it('skips when user declines update for existing key', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_PASSWORD' => 'OLD_SECRET'],
        ]));

        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'secret', [
                'secret' => 'Secret-backed (from GitHub Secrets)',
                'static' => 'Static (fixed value)',
                'build' => 'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'DB_PASSWORD')
            ->expectsConfirmation('"DB_PASSWORD" is already configured. Update it?', 'no')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping']['DB_PASSWORD'])->toBe('OLD_SECRET');
    });

    it('warns and offers update when static key already exists', function () {
        File::put($this->jsonPath, json_encode([
            'env_static' => ['SESSION_DRIVER' => 'file'],
        ]));

        $this->artisan('netsons:env', ['action' => 'add'])
            ->expectsChoice('What type of variable?', 'static', [
                'secret' => 'Secret-backed (from GitHub Secrets)',
                'static' => 'Static (fixed value)',
                'build' => 'Build (available during asset build)',
            ])
            ->expectsQuestion('ENV variable name', 'SESSION_DRIVER')
            ->expectsOutputToContain('already configured')
            ->expectsConfirmation('"SESSION_DRIVER" is already configured. Update it?', 'yes')
            ->expectsQuestion('Value', 'database')
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_static']['SESSION_DRIVER'])->toBe('database');
    });
});

describe('netsons:env remove', function () {
    it('removes a variable interactively', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_DATABASE' => 'DB_DATABASE', 'DB_USERNAME' => 'DB_USERNAME'],
        ]));

        $this->artisan('netsons:env', ['action' => 'remove'])
            ->expectsChoice('Which item to remove?', 'DB_DATABASE (secret: DB_DATABASE)', [
                'DB_DATABASE (secret: DB_DATABASE)' => 'DB_DATABASE (secret: DB_DATABASE)',
                'DB_USERNAME (secret: DB_USERNAME)' => 'DB_USERNAME (secret: DB_USERNAME)',
                '-- Cancel --' => '-- Cancel --',
            ])
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping'])->toBe(['DB_USERNAME' => 'DB_USERNAME']);
    });

    it('removes a custom command interactively', function () {
        File::put($this->jsonPath, json_encode([
            'custom_commands' => ['permission:cache-reset', 'horizon:terminate'],
        ]));

        $this->artisan('netsons:env', ['action' => 'remove'])
            ->expectsChoice('Which item to remove?', 'command: permission:cache-reset', [
                'command: permission:cache-reset' => 'command: permission:cache-reset',
                'command: horizon:terminate' => 'command: horizon:terminate',
                '-- Cancel --' => '-- Cancel --',
            ])
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['custom_commands'])->toBe(['horizon:terminate']);
    });

    it('removes slack notification interactively', function () {
        File::put($this->jsonPath, json_encode([
            'notifications' => ['slack_webhook_secret' => 'SLACK_WEBHOOK_DEBUG'],
        ]));

        $this->artisan('netsons:env', ['action' => 'remove'])
            ->expectsChoice('Which item to remove?', 'notification: Slack (SLACK_WEBHOOK_DEBUG)', [
                'notification: Slack (SLACK_WEBHOOK_DEBUG)' => 'notification: Slack (SLACK_WEBHOOK_DEBUG)',
                '-- Cancel --' => '-- Cancel --',
            ])
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['notifications'])->toBe([]);
    });

    it('shows all types in remove list', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_PASSWORD' => 'DB_PASSWORD'],
            'build_env' => ['VITE_APP_NAME' => 'My App'],
            'custom_commands' => ['permission:cache-reset'],
            'notifications' => ['slack_webhook_secret' => 'SLACK_WEBHOOK'],
        ]));

        $this->artisan('netsons:env', ['action' => 'remove'])
            ->expectsChoice('Which item to remove?', 'DB_PASSWORD (secret: DB_PASSWORD)', [
                'DB_PASSWORD (secret: DB_PASSWORD)' => 'DB_PASSWORD (secret: DB_PASSWORD)',
                'VITE_APP_NAME (build: My App)' => 'VITE_APP_NAME (build: My App)',
                'command: permission:cache-reset' => 'command: permission:cache-reset',
                'notification: Slack (SLACK_WEBHOOK)' => 'notification: Slack (SLACK_WEBHOOK)',
                '-- Cancel --' => '-- Cancel --',
            ])
            ->assertSuccessful();
    });

    it('cancels remove when user selects cancel', function () {
        File::put($this->jsonPath, json_encode([
            'env_mapping' => ['DB_PASSWORD' => 'DB_PASSWORD'],
        ]));

        $this->artisan('netsons:env', ['action' => 'remove'])
            ->expectsChoice('Which item to remove?', '-- Cancel --', [
                'DB_PASSWORD (secret: DB_PASSWORD)' => 'DB_PASSWORD (secret: DB_PASSWORD)',
                '-- Cancel --' => '-- Cancel --',
            ])
            ->assertSuccessful();

        $data = json_decode(File::get($this->jsonPath), true);
        expect($data['env_mapping'])->toBe(['DB_PASSWORD' => 'DB_PASSWORD']);
    });

    it('shows message when nothing to remove', function () {
        $this->artisan('netsons:env', ['action' => 'remove', '--no-interaction' => true])
            ->expectsOutputToContain('No items configured')
            ->assertSuccessful();
    });
});
