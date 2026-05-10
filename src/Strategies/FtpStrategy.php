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
