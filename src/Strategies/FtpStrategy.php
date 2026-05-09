<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Strategies;

class FtpStrategy implements DeployStrategyInterface
{
    public function name(): string
    {
        return 'ftp';
    }

    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['ssh']['host'])) {
            $errors[] = 'SSH host is required.';
        }

        if (empty($config['ssh']['user'])) {
            $errors[] = 'SSH user is required.';
        }

        if (empty($config['ftp']['host'])) {
            $errors[] = 'FTP host is required.';
        }

        if (empty($config['ftp']['user'])) {
            $errors[] = 'FTP user is required.';
        }

        if (empty($config['ftp']['password'])) {
            $errors[] = 'FTP password is required.';
        }

        if (empty($config['deploy_path'])) {
            $errors[] = 'Deploy path is required.';
        }

        return $errors;
    }

    public function requiredSecrets(): array
    {
        return [
            'SSH_PRIVATE_KEY',
            'SSH_KNOWN_HOSTS',
            'FTP_HOST',
            'FTP_USER',
            'FTP_PASS',
            'FTP_PORT',
        ];
    }

    public function requiredVariables(): array
    {
        return [
            'DEPLOY_PATH',
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
        ];
    }
}
