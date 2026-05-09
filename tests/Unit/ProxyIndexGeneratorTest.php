<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Services\ProxyIndexGenerator;

beforeEach(function () {
    $this->generator = new ProxyIndexGenerator();
});

describe('generate', function () {
    it('returns a non-empty string', function () {
        $result = $this->generator->generate('/home/user/releases/20240101120000');
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('starts with a PHP opening tag', function () {
        $result = $this->generator->generate('/home/user/releases/20240101120000');
        expect($result)->toStartWith('<?php');
    });

    it('contains the given release path', function () {
        $path = '/home/user/releases/20240101120000';
        $result = $this->generator->generate($path);
        expect($result)->toContain($path);
    });

    it('references index.php in the public directory', function () {
        $result = $this->generator->generate('/home/user/releases/20240101120000');
        expect($result)->toContain('public/index.php');
    });

    it('handles maintenance mode', function () {
        $result = $this->generator->generate('/home/user/releases/20240101120000');
        expect($result)->toContain('maintenance');
    });

    it('works with different release paths', function () {
        $path = '/home/other/public_html/releases/20250509143000';
        $result = $this->generator->generate($path);
        expect($result)->toContain($path);
    });
});
