<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Services\DeployConfigManager;
use Illuminate\Console\Command;

class EnvCommand extends Command
{
    protected $signature = 'netsons:env
                            {action? : Action to perform (list, add, remove)}';

    protected $description = 'Manage environment variable configuration for Netsons Deploy';

    protected DeployConfigManager $configManager;

    public function handle(): int
    {
        $this->configManager = new DeployConfigManager(base_path('netsons-deploy.json'));

        $action = $this->argument('action') ?? 'list';

        return match ($action) {
            'list' => $this->listVariables(),
            'add' => $this->addVariable(),
            'remove' => $this->removeVariable(),
            default => $this->invalidAction($action),
        };
    }

    protected function listVariables(): int
    {
        $this->info('');
        $this->info('  Netsons Deploy — Environment Configuration');
        $this->info('  ===========================================');
        $this->info('');

        $data = $this->configManager->read();
        $hasAny = false;

        // Secret-backed variables
        if (! empty($data['env_mapping'])) {
            $hasAny = true;
            $this->info('  Secret-backed variables (from GitHub Secrets):');
            $this->table(
                ['ENV Variable', 'GitHub Secret'],
                collect($data['env_mapping'])->map(fn (string $secret, string $key) => [$key, $secret])->values()->toArray()
            );
        }

        // Static variables
        if (! empty($data['env_static'])) {
            $hasAny = true;
            $this->info('  Static variables (fixed values):');
            $this->table(
                ['ENV Variable', 'Value'],
                collect($data['env_static'])->map(fn (string $value, string $key) => [$key, $value])->values()->toArray()
            );
        }

        // Build env variables
        if (! empty($data['build_env'])) {
            $hasAny = true;
            $this->info('  Build variables (available during asset build):');
            $this->table(
                ['ENV Variable', 'Value'],
                collect($data['build_env'])->map(fn (string $value, string $key) => [$key, $value])->values()->toArray()
            );
        }

        // Custom commands
        if (! empty($data['custom_commands'])) {
            $hasAny = true;
            $this->info('  Custom post-deploy commands:');
            $this->table(
                ['Command'],
                collect($data['custom_commands'])->map(fn (string $cmd) => [$cmd])->toArray()
            );
        }

        // Notifications
        $webhookSecret = $data['notifications']['slack_webhook_secret'] ?? '';
        if ($webhookSecret !== '') {
            $hasAny = true;
            $this->info('  Notifications:');
            $this->table(
                ['Type', 'Secret'],
                [['Slack webhook', $webhookSecret]]
            );
        }

        if (! $hasAny) {
            $this->info('  No custom environment variables configured.');
            $this->info('  Run "php artisan netsons:env add" to add variables.');
        }

        $this->info('');

        return self::SUCCESS;
    }

    protected function addVariable(): int
    {
        $types = [
            'Secret-backed (from GitHub Secrets)',
            'Static (fixed value)',
            'Build (available during asset build)',
        ];

        $type = $this->choice('What type of variable?', $types);

        match ($type) {
            'Secret-backed (from GitHub Secrets)' => $this->addSecretBacked(),
            'Static (fixed value)' => $this->addStatic(),
            'Build (available during asset build)' => $this->addBuild(),
        };

        $this->info('  Variable added to netsons-deploy.json.');
        $this->info('  Run "php artisan netsons:install --force" to regenerate the workflow.');

        return self::SUCCESS;
    }

    protected function addSecretBacked(): void
    {
        $envKey = $this->ask('ENV variable name');
        $secretName = $this->ask('GitHub Secret name (default: same as ENV name)', $envKey);

        $this->configManager->addEnvMapping($envKey, $secretName);
    }

    protected function addStatic(): void
    {
        $envKey = $this->ask('ENV variable name');
        $value = $this->ask('Value');

        $this->configManager->addEnvStatic($envKey, $value);
    }

    protected function addBuild(): void
    {
        $envKey = $this->ask('ENV variable name');
        $value = $this->ask('Value');

        $this->configManager->addBuildEnv($envKey, $value);
    }

    protected function removeVariable(): int
    {
        $data = $this->configManager->read();
        $choices = [];

        foreach ($data['env_mapping'] as $key => $secret) {
            $choices[] = "{$key} (secret: {$secret})";
        }
        foreach ($data['env_static'] as $key => $value) {
            $choices[] = "{$key} (static: {$value})";
        }
        foreach ($data['build_env'] as $key => $value) {
            $choices[] = "{$key} (build: {$value})";
        }

        if (empty($choices)) {
            $this->info('  No variables configured to remove.');

            return self::SUCCESS;
        }

        $selected = $this->choice('Which variable to remove?', $choices);

        // Parse the selection to determine type and key
        if (preg_match('/^(\S+)\s+\(secret:/', $selected, $matches)) {
            $this->configManager->removeEnvMapping($matches[1]);
        } elseif (preg_match('/^(\S+)\s+\(static:/', $selected, $matches)) {
            $this->configManager->removeEnvStatic($matches[1]);
        } elseif (preg_match('/^(\S+)\s+\(build:/', $selected, $matches)) {
            $this->configManager->removeBuildEnv($matches[1]);
        }

        $this->info('  Variable removed from netsons-deploy.json.');
        $this->info('  Run "php artisan netsons:install --force" to regenerate the workflow.');

        return self::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("  Unknown action: {$action}. Use list, add, or remove.");

        return self::FAILURE;
    }
}
