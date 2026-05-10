<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Services\ReleaseManager;

beforeEach(function () {
    $this->manager = new ReleaseManager;
});

describe('generateTimestamp', function () {
    it('returns a 14-character string', function () {
        $result = $this->manager->generateTimestamp();
        expect($result)->toHaveLength(14);
    });

    it('contains only digits', function () {
        $result = $this->manager->generateTimestamp();
        expect($result)->toMatch('/^\d{14}$/');
    });

    it('starts with the current year', function () {
        $result = $this->manager->generateTimestamp();
        $year = gmdate('Y');
        expect($result)->toStartWith($year);
    });
});

describe('getCleanupCommand', function () {
    it('returns a non-empty string', function () {
        $result = $this->manager->getCleanupCommand('public_html', 5);
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('contains the deploy path', function () {
        $result = $this->manager->getCleanupCommand('public_html', 5);
        expect($result)->toContain('public_html');
    });

    it('references the releases directory', function () {
        $result = $this->manager->getCleanupCommand('public_html', 5);
        expect($result)->toContain('releases');
    });

    it('respects the keep count', function () {
        $result = $this->manager->getCleanupCommand('public_html', 3);
        expect($result)->toContain('3');
    });
});

describe('getCreateCommand', function () {
    it('returns a non-empty string', function () {
        $result = $this->manager->getCreateCommand('public_html', '20240101120000');
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('contains the deploy path and timestamp', function () {
        $result = $this->manager->getCreateCommand('public_html', '20240101120000');
        expect($result)->toContain('public_html');
        expect($result)->toContain('20240101120000');
    });

    it('creates a directory under releases', function () {
        $result = $this->manager->getCreateCommand('public_html', '20240101120000');
        expect($result)->toContain('releases/20240101120000');
    });

    it('uses mkdir', function () {
        $result = $this->manager->getCreateCommand('public_html', '20240101120000');
        expect($result)->toContain('mkdir');
    });
});

describe('getSwitchCommand', function () {
    it('returns a non-empty string', function () {
        $result = $this->manager->getSwitchCommand('public_html', '20240101120000', '/usr/local/bin/ea-php84');
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('contains the deploy path and timestamp', function () {
        $result = $this->manager->getSwitchCommand('public_html', '20240101120000', '/usr/local/bin/ea-php84');
        expect($result)->toContain('public_html');
        expect($result)->toContain('20240101120000');
    });

    it('references the PHP binary', function () {
        $result = $this->manager->getSwitchCommand('public_html', '20240101120000', '/usr/local/bin/ea-php84');
        expect($result)->toContain('/usr/local/bin/ea-php84');
    });

    it('clears and rebuilds caches', function () {
        $result = $this->manager->getSwitchCommand('public_html', '20240101120000', '/usr/local/bin/ea-php84');
        expect($result)->toContain('cache:clear');
        expect($result)->toContain('config:cache');
    });

    it('references the current symlink', function () {
        $result = $this->manager->getSwitchCommand('public_html', '20240101120000', '/usr/local/bin/ea-php84');
        expect($result)->toContain('current');
    });
});
