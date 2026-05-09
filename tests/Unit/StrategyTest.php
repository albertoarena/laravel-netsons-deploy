<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\Strategies\DeployStrategyInterface;
use AlbertoArena\NetsonsDeploy\Strategies\FtpStrategy;
use AlbertoArena\NetsonsDeploy\Strategies\GitStrategy;

describe('FtpStrategy', function () {
    beforeEach(function () {
        $this->strategy = new FtpStrategy();
    });

    it('implements DeployStrategyInterface', function () {
        expect($this->strategy)->toBeInstanceOf(DeployStrategyInterface::class);
    });

    it('returns ftp as the strategy name', function () {
        expect($this->strategy->name())->toBe('ftp');
    });

    it('requires SSH and FTP secrets', function () {
        $secrets = $this->strategy->requiredSecrets();
        expect($secrets)->toBeArray();
        expect($secrets)->toContain('SSH_PRIVATE_KEY');
        expect($secrets)->toContain('SSH_KNOWN_HOSTS');
        expect($secrets)->toContain('FTP_HOST');
        expect($secrets)->toContain('FTP_USER');
        expect($secrets)->toContain('FTP_PASS');
        expect($secrets)->toContain('FTP_PORT');
    });

    it('requires deployment variables', function () {
        $variables = $this->strategy->requiredVariables();
        expect($variables)->toBeArray();
        expect($variables)->toContain('DEPLOY_PATH');
        expect($variables)->toContain('APP_ENV');
        expect($variables)->toContain('APP_DEBUG');
        expect($variables)->toContain('APP_URL');
    });

    it('returns no errors for valid config', function () {
        $config = [
            'strategy' => 'ftp',
            'ssh' => ['host' => 'example.com', 'port' => 65100, 'user' => 'deploy'],
            'ftp' => ['host' => 'ftp.example.com', 'port' => 21, 'user' => 'ftpuser', 'password' => 'secret', 'protocol' => 'ftp'],
            'deploy_path' => 'public_html',
        ];
        $errors = $this->strategy->validate($config);
        expect($errors)->toBeArray()->toBeEmpty();
    });

    it('returns errors when SSH host is missing', function () {
        $config = [
            'strategy' => 'ftp',
            'ssh' => ['host' => null, 'port' => 65100, 'user' => 'deploy'],
            'ftp' => ['host' => 'ftp.example.com', 'port' => 21, 'user' => 'ftpuser', 'password' => 'secret', 'protocol' => 'ftp'],
            'deploy_path' => 'public_html',
        ];
        $errors = $this->strategy->validate($config);
        expect($errors)->not->toBeEmpty();
    });

    it('returns errors when FTP host is missing', function () {
        $config = [
            'strategy' => 'ftp',
            'ssh' => ['host' => 'example.com', 'port' => 65100, 'user' => 'deploy'],
            'ftp' => ['host' => null, 'port' => 21, 'user' => 'ftpuser', 'password' => 'secret', 'protocol' => 'ftp'],
            'deploy_path' => 'public_html',
        ];
        $errors = $this->strategy->validate($config);
        expect($errors)->not->toBeEmpty();
    });
});

describe('GitStrategy', function () {
    beforeEach(function () {
        $this->strategy = new GitStrategy();
    });

    it('implements DeployStrategyInterface', function () {
        expect($this->strategy)->toBeInstanceOf(DeployStrategyInterface::class);
    });

    it('returns git as the strategy name', function () {
        expect($this->strategy->name())->toBe('git');
    });

    it('requires SSH secrets', function () {
        $secrets = $this->strategy->requiredSecrets();
        expect($secrets)->toBeArray();
        expect($secrets)->toContain('SSH_PRIVATE_KEY');
        expect($secrets)->toContain('SSH_KNOWN_HOSTS');
    });

    it('does not require FTP secrets', function () {
        $secrets = $this->strategy->requiredSecrets();
        expect($secrets)->not->toContain('FTP_HOST');
        expect($secrets)->not->toContain('FTP_USER');
        expect($secrets)->not->toContain('FTP_PASS');
    });

    it('requires git-specific variables', function () {
        $variables = $this->strategy->requiredVariables();
        expect($variables)->toBeArray();
        expect($variables)->toContain('DEPLOY_PATH');
        expect($variables)->toContain('APP_ENV');
        expect($variables)->toContain('APP_DEBUG');
        expect($variables)->toContain('APP_URL');
        expect($variables)->toContain('GIT_REPO');
        expect($variables)->toContain('GIT_BRANCH');
    });

    it('returns no errors for valid config', function () {
        $config = [
            'strategy' => 'git',
            'ssh' => ['host' => 'example.com', 'port' => 65100, 'user' => 'deploy'],
            'git' => ['repo' => 'git@github.com:user/repo.git', 'branch' => 'main'],
            'deploy_path' => 'public_html',
        ];
        $errors = $this->strategy->validate($config);
        expect($errors)->toBeArray()->toBeEmpty();
    });

    it('returns errors when SSH host is missing', function () {
        $config = [
            'strategy' => 'git',
            'ssh' => ['host' => null, 'port' => 65100, 'user' => 'deploy'],
            'git' => ['repo' => 'git@github.com:user/repo.git', 'branch' => 'main'],
            'deploy_path' => 'public_html',
        ];
        $errors = $this->strategy->validate($config);
        expect($errors)->not->toBeEmpty();
    });

    it('returns errors when git repo is missing', function () {
        $config = [
            'strategy' => 'git',
            'ssh' => ['host' => 'example.com', 'port' => 65100, 'user' => 'deploy'],
            'git' => ['repo' => null, 'branch' => 'main'],
            'deploy_path' => 'public_html',
        ];
        $errors = $this->strategy->validate($config);
        expect($errors)->not->toBeEmpty();
    });
});
