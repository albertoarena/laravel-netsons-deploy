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
    // Clean up all files in temp dir
    if (is_dir($this->tempDir)) {
        $files = glob($this->tempDir.'/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        // Also clean hidden files like .env.example
        $hidden = glob($this->tempDir.'/.*') ?: [];
        foreach ($hidden as $file) {
            if (basename($file) !== '.' && basename($file) !== '..') {
                unlink($file);
            }
        }
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

describe('parseEnvExample', function () {
    it('returns empty arrays when file does not exist', function () {
        $result = DeployConfigManager::parseEnvExample('/nonexistent/.env.example');

        expect($result['secret'])->toBe([]);
        expect($result['static'])->toBe([]);
    });

    it('detects DB variables as secret-backed', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'DB_DATABASE=laravel',
            'DB_USERNAME=root',
            'DB_PASSWORD=',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['secret'])->toContain('DB_DATABASE');
        expect($result['secret'])->toContain('DB_USERNAME');
        expect($result['secret'])->toContain('DB_PASSWORD');
    });

    it('detects MAIL credentials as secret-backed', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'MAIL_HOST=mailpit',
            'MAIL_USERNAME=null',
            'MAIL_PASSWORD=null',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['secret'])->toContain('MAIL_USERNAME');
        expect($result['secret'])->toContain('MAIL_PASSWORD');
    });

    it('detects REDIS_PASSWORD as secret-backed', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'REDIS_HOST=127.0.0.1',
            'REDIS_PASSWORD=null',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['secret'])->toContain('REDIS_PASSWORD');
        expect($result['secret'])->not->toContain('REDIS_HOST');
    });

    it('detects SESSION_DRIVER as static when non-default', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'SESSION_DRIVER=database',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe(['SESSION_DRIVER' => 'database']);
    });

    it('detects SESSION_DRIVER even with default value', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'SESSION_DRIVER=file',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe(['SESSION_DRIVER' => 'file']);
    });

    it('excludes APP_* variables', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'APP_NAME=Laravel',
            'APP_ENV=local',
            'APP_KEY=',
            'APP_DEBUG=true',
            'APP_URL=http://localhost',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['secret'])->toBe([]);
        expect($result['static'])->toBe([]);
    });

    it('skips comment lines and empty lines', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            '# Database settings',
            '',
            'DB_PASSWORD=secret',
            '# End',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['secret'])->toBe(['DB_PASSWORD']);
    });

    it('detects project-specific static variables', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'LARAVEL_PDF_DRIVER=dompdf',
            'SCOUT_DRIVER=meilisearch',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe([
            'LARAVEL_PDF_DRIVER' => 'dompdf',
            'SCOUT_DRIVER' => 'meilisearch',
        ]);
    });

    it('skips empty and null values for static detection', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'SOME_KEY=',
            'ANOTHER_KEY=null',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe([]);
    });

    it('skips boolean values for static detection', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'TELESCOPE_ENABLED=false',
            'DEBUGBAR_ENABLED=true',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe([]);
    });

    it('skips localhost and numeric values for static detection', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'SOME_HOST=127.0.0.1',
            'ANOTHER_HOST=localhost',
            'SOME_PORT=3306',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe([]);
    });

    it('detects VITE_* as build variables', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'VITE_APP_NAME=MyApp',
            'VITE_API_URL=http://localhost',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['build'])->toBe([
            'VITE_APP_NAME' => 'MyApp',
            'VITE_API_URL' => 'http://localhost',
        ]);
        expect($result['static'])->toBe([]);
    });

    it('skips VITE_* with placeholder values', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'VITE_APP_NAME=',
            'VITE_OTHER=null',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['build'])->toBe([]);
    });

    it('returns empty build array when no VITE_* found', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'SESSION_DRIVER=database',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['build'])->toBe([]);
    });

    it('excludes DB_* MAIL_* REDIS_* prefixes from static detection', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'DB_CONNECTION=mysql',
            'MAIL_MAILER=smtp',
            'REDIS_HOST=127.0.0.1',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['static'])->toBe([]);
    });

    it('detects AWS keys as secret-backed', function () {
        $envFile = $this->tempDir.'/.env.example';
        file_put_contents($envFile, implode("\n", [
            'AWS_ACCESS_KEY_ID=',
            'AWS_SECRET_ACCESS_KEY=',
        ]));

        $result = DeployConfigManager::parseEnvExample($envFile);

        expect($result['secret'])->toContain('AWS_ACCESS_KEY_ID');
        expect($result['secret'])->toContain('AWS_SECRET_ACCESS_KEY');
    });
});

describe('envaudit', function () {
    it('defaults envaudit to false', function () {
        $data = $this->manager->read();

        expect($data['envaudit'])->toBeFalse();
    });

    it('reads envaudit when set to true', function () {
        file_put_contents($this->jsonPath, json_encode([
            'envaudit' => true,
        ]));

        $data = $this->manager->read();
        expect($data['envaudit'])->toBeTrue();
    });

    it('sets envaudit to true', function () {
        $this->manager->setEnvaudit(true);

        $data = $this->manager->read();
        expect($data['envaudit'])->toBeTrue();
    });

    it('sets envaudit to false', function () {
        $this->manager->setEnvaudit(true);
        $this->manager->setEnvaudit(false);

        $data = $this->manager->read();
        expect($data['envaudit'])->toBeFalse();
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
