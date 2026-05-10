<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Services\HtaccessGenerator;

beforeEach(function () {
    $this->generator = new HtaccessGenerator;
});

describe('generateRoot', function () {
    it('returns a non-empty string', function () {
        $result = $this->generator->generateRoot();
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('enables the rewrite engine', function () {
        $result = $this->generator->generateRoot();
        expect($result)->toContain('RewriteEngine On');
    });

    it('rewrites requests to the public directory', function () {
        $result = $this->generator->generateRoot();
        expect($result)->toContain('public/');
    });

    it('contains RewriteRule directives', function () {
        $result = $this->generator->generateRoot();
        expect($result)->toContain('RewriteRule');
    });
});

describe('generatePublic', function () {
    it('returns a non-empty string', function () {
        $result = $this->generator->generatePublic();
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('enables the rewrite engine', function () {
        $result = $this->generator->generatePublic();
        expect($result)->toContain('RewriteEngine On');
    });

    it('has the front controller rewrite to index.php', function () {
        $result = $this->generator->generatePublic();
        expect($result)->toContain('index.php');
    });

    it('handles the authorization header', function () {
        $result = $this->generator->generatePublic();
        expect($result)->toContain('Authorization');
    });

    it('handles the XSRF token header', function () {
        $result = $this->generator->generatePublic();
        expect($result)->toContain('X-XSRF-TOKEN');
    });

    it('redirects trailing slashes', function () {
        $result = $this->generator->generatePublic();
        expect($result)->toContain('trailing');
    });
});
