<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Services\DeployConfigManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/netsons-deploy-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->jsonPath = $this->tempDir.'/netsons-deploy.json';
    $this->manager = new DeployConfigManager($this->jsonPath);
});

afterEach(function () {
    if (file_exists($this->jsonPath)) {
        unlink($this->jsonPath);
    }
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

describe('read', function () {
    it('returns default structure when JSON file does not exist', function () {
        $data = $this->manager->read();

        expect($data)->toBeArray();
        expect($data['env_mapping'])->toBe([]);
        expect($data['env_static'])->toBe([]);
        expect($data['build_env'])->toBe([]);
        expect($data['custom_commands'])->toBe([]);
        expect($data['notifications'])->toBe([]);
    });

    it('reads existing JSON file', function () {
        file_put_contents($this->jsonPath, json_encode([
            'env_mapping' => ['DB_DATABASE' => 'DB_DATABASE'],
            'env_static' => ['SESSION_DRIVER' => 'database'],
        ]));

        $data = $this->manager->read();

        expect($data['env_mapping'])->toBe(['DB_DATABASE' => 'DB_DATABASE']);
        expect($data['env_static'])->toBe(['SESSION_DRIVER' => 'database']);
        expect($data['build_env'])->toBe([]);
        expect($data['custom_commands'])->toBe([]);
        expect($data['notifications'])->toBe([]);
    });

    it('merges partial JSON with defaults', function () {
        file_put_contents($this->jsonPath, json_encode([
            'env_mapping' => ['DB_HOST' => 'DB_HOST'],
        ]));

        $data = $this->manager->read();

        expect($data['env_mapping'])->toBe(['DB_HOST' => 'DB_HOST']);
        expect($data['env_static'])->toBe([]);
        expect($data['build_env'])->toBe([]);
        expect($data['custom_commands'])->toBe([]);
        expect($data['notifications'])->toBe([]);
    });
});

describe('write', function () {
    it('writes data to JSON file', function () {
        $this->manager->write([
            'env_mapping' => ['DB_DATABASE' => 'DB_DATABASE'],
            'env_static' => [],
            'build_env' => [],
            'custom_commands' => [],
            'notifications' => [],
        ]);

        expect(file_exists($this->jsonPath))->toBeTrue();

        $content = json_decode(file_get_contents($this->jsonPath), true);
        expect($content['env_mapping'])->toBe(['DB_DATABASE' => 'DB_DATABASE']);
    });

    it('writes pretty-printed JSON', function () {
        $this->manager->write([
            'env_mapping' => ['KEY' => 'VALUE'],
            'env_static' => [],
            'build_env' => [],
            'custom_commands' => [],
            'notifications' => [],
        ]);

        $raw = file_get_contents($this->jsonPath);
        expect($raw)->toContain("\n");
    });

    it('overwrites existing file', function () {
        file_put_contents($this->jsonPath, json_encode(['env_mapping' => ['OLD' => 'OLD']]));

        $this->manager->write([
            'env_mapping' => ['NEW' => 'NEW'],
            'env_static' => [],
            'build_env' => [],
            'custom_commands' => [],
            'notifications' => [],
        ]);

        $content = json_decode(file_get_contents($this->jsonPath), true);
        expect($content['env_mapping'])->toBe(['NEW' => 'NEW']);
    });
});

describe('addEnvMapping', function () {
    it('adds a secret-backed env mapping', function () {
        $this->manager->addEnvMapping('DB_PASSWORD', 'DB_PASSWORD');

        $data = $this->manager->read();
        expect($data['env_mapping'])->toBe(['DB_PASSWORD' => 'DB_PASSWORD']);
    });

    it('adds mapping with different secret name', function () {
        $this->manager->addEnvMapping('DB_PASSWORD', 'PROD_DB_PASS');

        $data = $this->manager->read();
        expect($data['env_mapping'])->toBe(['DB_PASSWORD' => 'PROD_DB_PASS']);
    });

    it('appends to existing mappings', function () {
        $this->manager->addEnvMapping('DB_DATABASE', 'DB_DATABASE');
        $this->manager->addEnvMapping('DB_USERNAME', 'DB_USERNAME');

        $data = $this->manager->read();
        expect($data['env_mapping'])->toBe([
            'DB_DATABASE' => 'DB_DATABASE',
            'DB_USERNAME' => 'DB_USERNAME',
        ]);
    });

    it('overwrites existing key', function () {
        $this->manager->addEnvMapping('DB_PASSWORD', 'OLD_SECRET');
        $this->manager->addEnvMapping('DB_PASSWORD', 'NEW_SECRET');

        $data = $this->manager->read();
        expect($data['env_mapping'])->toBe(['DB_PASSWORD' => 'NEW_SECRET']);
    });
});

describe('addEnvStatic', function () {
    it('adds a static env variable', function () {
        $this->manager->addEnvStatic('SESSION_DRIVER', 'database');

        $data = $this->manager->read();
        expect($data['env_static'])->toBe(['SESSION_DRIVER' => 'database']);
    });

    it('appends to existing static variables', function () {
        $this->manager->addEnvStatic('SESSION_DRIVER', 'database');
        $this->manager->addEnvStatic('LARAVEL_PDF_DRIVER', 'dompdf');

        $data = $this->manager->read();
        expect($data['env_static'])->toBe([
            'SESSION_DRIVER' => 'database',
            'LARAVEL_PDF_DRIVER' => 'dompdf',
        ]);
    });

    it('overwrites existing key', function () {
        $this->manager->addEnvStatic('SESSION_DRIVER', 'file');
        $this->manager->addEnvStatic('SESSION_DRIVER', 'database');

        $data = $this->manager->read();
        expect($data['env_static'])->toBe(['SESSION_DRIVER' => 'database']);
    });
});

describe('addBuildEnv', function () {
    it('adds a build env variable', function () {
        $this->manager->addBuildEnv('VITE_APP_NAME', 'My App');

        $data = $this->manager->read();
        expect($data['build_env'])->toBe(['VITE_APP_NAME' => 'My App']);
    });

    it('appends to existing build env variables', function () {
        $this->manager->addBuildEnv('VITE_APP_NAME', 'My App');
        $this->manager->addBuildEnv('VITE_API_URL', 'https://api.example.com');

        $data = $this->manager->read();
        expect($data['build_env'])->toHaveCount(2);
        expect($data['build_env']['VITE_APP_NAME'])->toBe('My App');
        expect($data['build_env']['VITE_API_URL'])->toBe('https://api.example.com');
    });
});

describe('addCustomCommand', function () {
    it('adds a custom artisan command', function () {
        $this->manager->addCustomCommand('event-sourcing:cache-event-handlers 2>/dev/null || true');

        $data = $this->manager->read();
        expect($data['custom_commands'])->toBe([
            'event-sourcing:cache-event-handlers 2>/dev/null || true',
        ]);
    });

    it('appends to existing commands', function () {
        $this->manager->addCustomCommand('permission:cache-reset');
        $this->manager->addCustomCommand('horizon:terminate');

        $data = $this->manager->read();
        expect($data['custom_commands'])->toBe([
            'permission:cache-reset',
            'horizon:terminate',
        ]);
    });

    it('does not add duplicate commands', function () {
        $this->manager->addCustomCommand('permission:cache-reset');
        $this->manager->addCustomCommand('permission:cache-reset');

        $data = $this->manager->read();
        expect($data['custom_commands'])->toBe(['permission:cache-reset']);
    });
});

describe('removeEnvMapping', function () {
    it('removes an env mapping', function () {
        $this->manager->addEnvMapping('DB_DATABASE', 'DB_DATABASE');
        $this->manager->addEnvMapping('DB_USERNAME', 'DB_USERNAME');
        $this->manager->removeEnvMapping('DB_DATABASE');

        $data = $this->manager->read();
        expect($data['env_mapping'])->toBe(['DB_USERNAME' => 'DB_USERNAME']);
    });

    it('does nothing when key does not exist', function () {
        $this->manager->addEnvMapping('DB_DATABASE', 'DB_DATABASE');
        $this->manager->removeEnvMapping('NONEXISTENT');

        $data = $this->manager->read();
        expect($data['env_mapping'])->toBe(['DB_DATABASE' => 'DB_DATABASE']);
    });
});

describe('removeEnvStatic', function () {
    it('removes a static env variable', function () {
        $this->manager->addEnvStatic('SESSION_DRIVER', 'database');
        $this->manager->addEnvStatic('LARAVEL_PDF_DRIVER', 'dompdf');
        $this->manager->removeEnvStatic('SESSION_DRIVER');

        $data = $this->manager->read();
        expect($data['env_static'])->toBe(['LARAVEL_PDF_DRIVER' => 'dompdf']);
    });
});

describe('removeBuildEnv', function () {
    it('removes a build env variable', function () {
        $this->manager->addBuildEnv('VITE_APP_NAME', 'My App');
        $this->manager->addBuildEnv('VITE_API_URL', 'https://api.example.com');
        $this->manager->removeBuildEnv('VITE_APP_NAME');

        $data = $this->manager->read();
        expect($data['build_env'])->toBe(['VITE_API_URL' => 'https://api.example.com']);
    });
});

describe('removeCustomCommand', function () {
    it('removes a custom command', function () {
        $this->manager->addCustomCommand('permission:cache-reset');
        $this->manager->addCustomCommand('horizon:terminate');
        $this->manager->removeCustomCommand('permission:cache-reset');

        $data = $this->manager->read();
        expect($data['custom_commands'])->toBe(['horizon:terminate']);
    });

    it('does nothing when command does not exist', function () {
        $this->manager->addCustomCommand('permission:cache-reset');
        $this->manager->removeCustomCommand('nonexistent:command');

        $data = $this->manager->read();
        expect($data['custom_commands'])->toBe(['permission:cache-reset']);
    });
});

describe('setSlackWebhook', function () {
    it('sets slack webhook secret name', function () {
        $this->manager->setSlackWebhook('SLACK_WEBHOOK_DEBUG');

        $data = $this->manager->read();
        expect($data['notifications'])->toBe(['slack_webhook_secret' => 'SLACK_WEBHOOK_DEBUG']);
    });

    it('clears slack webhook when null is passed', function () {
        $this->manager->setSlackWebhook('SLACK_WEBHOOK_DEBUG');
        $this->manager->setSlackWebhook(null);

        $data = $this->manager->read();
        expect($data['notifications'])->toBe([]);
    });
});

describe('has', function () {
    it('returns false when env_mapping key does not exist', function () {
        expect($this->manager->has('env_mapping', 'DB_PASSWORD'))->toBeFalse();
    });

    it('returns true when env_mapping key exists', function () {
        $this->manager->addEnvMapping('DB_PASSWORD', 'DB_PASSWORD');

        expect($this->manager->has('env_mapping', 'DB_PASSWORD'))->toBeTrue();
    });

    it('returns false when env_static key does not exist', function () {
        expect($this->manager->has('env_static', 'SESSION_DRIVER'))->toBeFalse();
    });

    it('returns true when env_static key exists', function () {
        $this->manager->addEnvStatic('SESSION_DRIVER', 'database');

        expect($this->manager->has('env_static', 'SESSION_DRIVER'))->toBeTrue();
    });

    it('returns false when build_env key does not exist', function () {
        expect($this->manager->has('build_env', 'VITE_APP_NAME'))->toBeFalse();
    });

    it('returns true when build_env key exists', function () {
        $this->manager->addBuildEnv('VITE_APP_NAME', 'My App');

        expect($this->manager->has('build_env', 'VITE_APP_NAME'))->toBeTrue();
    });

    it('returns false for unknown section', function () {
        expect($this->manager->has('nonexistent', 'KEY'))->toBeFalse();
    });
});

describe('exists', function () {
    it('returns false when file does not exist', function () {
        expect($this->manager->exists())->toBeFalse();
    });

    it('returns true when file exists', function () {
        $this->manager->write([
            'env_mapping' => [],
            'env_static' => [],
            'build_env' => [],
            'custom_commands' => [],
            'notifications' => [],
        ]);

        expect($this->manager->exists())->toBeTrue();
    });
});
