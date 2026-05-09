<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Services\EnvManager;

beforeEach(function () {
    $this->manager = new EnvManager();
});

describe('escapeForSed', function () {
    it('returns unmodified simple strings', function () {
        expect($this->manager->escapeForSed('hello'))->toBe('hello');
    });

    it('escapes forward slashes', function () {
        $result = $this->manager->escapeForSed('http://example.com');
        expect($result)->toContain('\\/');
        expect($result)->toBe('http:\\/\\/example.com');
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
});
