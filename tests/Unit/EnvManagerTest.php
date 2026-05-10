<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Services\EnvManager;

beforeEach(function () {
    $this->manager = new EnvManager;
});

describe('escapeForSed', function () {
    it('returns unmodified simple strings', function () {
        expect($this->manager->escapeForSed('hello'))->toBe('hello');
    });

    it('escapes pipe characters', function () {
        $result = $this->manager->escapeForSed('foo|bar');
        expect($result)->toBe('foo\\|bar');
    });

    it('escapes ampersands', function () {
        $result = $this->manager->escapeForSed('foo&bar');
        expect($result)->toContain('\\&');
    });

    it('escapes backslashes', function () {
        $result = $this->manager->escapeForSed('path\\to\\file');
        expect($result)->toContain('\\\\');
    });

    it('handles values with double quotes', function () {
        $result = $this->manager->escapeForSed('say "hello"');
        expect($result)->not->toBe('say "hello"');
    });

    it('does not escape forward slashes when using pipe delimiter', function () {
        $result = $this->manager->escapeForSed('http://example.com');
        expect($result)->toBe('http://example.com');
    });

    it('handles passwords with multiple special characters', function () {
        $result = $this->manager->escapeForSed('p@ss&w/rd\\123|test');
        expect($result)->toBe('p@ss\\&w/rd\\\\123\\|test');
    });
});

describe('generateSedCommands', function () {
    it('returns an empty string for empty mapping', function () {
        expect($this->manager->generateSedCommands([]))->toBe('');
    });

    it('generates a sed command for a single key-value pair', function () {
        $result = $this->manager->generateSedCommands(['APP_KEY' => 'base64:abc123']);
        expect($result)->toContain('APP_KEY');
        expect($result)->toContain('sed');
    });

    it('generates multiple sed commands for multiple pairs', function () {
        $mapping = [
            'APP_KEY' => 'base64:abc123',
            'DB_HOST' => 'localhost',
        ];
        $result = $this->manager->generateSedCommands($mapping);
        expect($result)->toContain('APP_KEY');
        expect($result)->toContain('DB_HOST');
    });

    it('handles values with special characters', function () {
        $mapping = ['APP_URL' => 'https://example.com/path?q=1&r=2'];
        $result = $this->manager->generateSedCommands($mapping);
        expect($result)->toContain('APP_URL');
        expect($result)->toContain('sed');
    });

    it('uses in-place sed editing', function () {
        $result = $this->manager->generateSedCommands(['KEY' => 'value']);
        expect($result)->toContain('sed -i');
    });

    it('targets the .env file', function () {
        $result = $this->manager->generateSedCommands(['KEY' => 'value']);
        expect($result)->toContain('.env');
    });

    it('uses pipe delimiter in sed commands', function () {
        $result = $this->manager->generateSedCommands(['KEY' => 'value']);
        expect($result)->toContain('s|^KEY=');
    });
});

describe('generateWorkflowEnvBlock', function () {
    it('returns empty string for empty mapping', function () {
        expect($this->manager->generateWorkflowEnvBlock([]))->toBe('');
    });

    it('generates secret references for env_mapping', function () {
        $result = $this->manager->generateWorkflowEnvBlock([
            'DB_DATABASE' => 'DB_DATABASE',
        ]);
        expect($result)->toContain('DB_DATABASE: ${{ secrets.DB_DATABASE }}');
    });

    it('generates secret references with different secret name', function () {
        $result = $this->manager->generateWorkflowEnvBlock([
            'DB_PASSWORD' => 'PROD_DB_PASS',
        ]);
        expect($result)->toContain('DB_PASSWORD: ${{ secrets.PROD_DB_PASS }}');
    });

    it('generates multiple lines for multiple mappings', function () {
        $result = $this->manager->generateWorkflowEnvBlock([
            'DB_DATABASE' => 'DB_DATABASE',
            'DB_USERNAME' => 'DB_USERNAME',
        ]);
        $lines = array_filter(explode("\n", $result));
        expect($lines)->toHaveCount(2);
    });

    it('uses consistent indentation', function () {
        $result = $this->manager->generateWorkflowEnvBlock(
            ['DB_DATABASE' => 'DB_DATABASE'],
            '    '
        );
        expect($result)->toStartWith('    DB_DATABASE:');
    });
});

describe('generateWorkflowSedBlock', function () {
    it('returns empty string when both mappings are empty', function () {
        expect($this->manager->generateWorkflowSedBlock([], []))->toBe('');
    });

    it('generates escaped sed for secret-backed variables', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            ['DB_PASSWORD' => 'DB_PASSWORD'],
            []
        );
        expect($result)->toContain('ESCAPED=');
        expect($result)->toContain('printf');
        expect($result)->toContain('DB_PASSWORD');
        expect($result)->toContain('sed -i');
    });

    it('generates direct sed for static variables', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            [],
            ['SESSION_DRIVER' => 'database']
        );
        expect($result)->toContain('SESSION_DRIVER');
        expect($result)->toContain('database');
        expect($result)->toContain('sed -i');
        expect($result)->not->toContain('ESCAPED=');
    });

    it('generates both secret and static blocks', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            ['DB_PASSWORD' => 'DB_PASSWORD'],
            ['SESSION_DRIVER' => 'database']
        );
        expect($result)->toContain('DB_PASSWORD');
        expect($result)->toContain('SESSION_DRIVER');
    });

    it('uses pipe delimiter for sed', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            ['DB_HOST' => 'DB_HOST'],
            ['SESSION_DRIVER' => 'database']
        );
        expect($result)->toContain('s|^DB_HOST=');
        expect($result)->toContain('s|^SESSION_DRIVER=');
    });

    it('uses runtime escaping for secret values', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            ['DB_PASSWORD' => 'DB_PASSWORD'],
            []
        );
        // Secret values are escaped at runtime with printf + sed
        expect($result)->toContain("printf '%s' \"\${DB_PASSWORD}\"");
        expect($result)->toContain('s/[|&\\\\]/\\\\&/g');
    });

    it('targets ENV_FILE variable', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            ['KEY' => 'KEY'],
            []
        );
        expect($result)->toContain('${ENV_FILE}');
    });

    it('uses consistent indentation', function () {
        $result = $this->manager->generateWorkflowSedBlock(
            ['KEY' => 'KEY'],
            [],
            '        '
        );
        expect($result)->toStartWith('        ');
    });
});
