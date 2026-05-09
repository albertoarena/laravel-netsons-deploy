<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Strategies;

class GitStrategy implements DeployStrategyInterface
{
    public function name(): string
    {
        return 'git';
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

        if (empty($config['git']['repo'])) {
            $errors[] = 'Git repository URL is required.';
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
        ];
    }

    public function requiredVariables(): array
    {
        return [
            'DEPLOY_PATH',
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
            'GIT_REPO',
            'GIT_BRANCH',
        ];
    }
}
