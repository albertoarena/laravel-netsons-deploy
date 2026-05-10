<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Services\DeployConfigManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

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
        note('Netsons Deploy — Environment Configuration');

        $data = $this->configManager->read();
        $hasAny = false;

        // Secret-backed variables
        if (! empty($data['env_mapping'])) {
            $hasAny = true;
            info('Secret-backed variables (from GitHub Secrets):');
            table(
                ['ENV Variable', 'GitHub Secret'],
                collect($data['env_mapping'])->map(fn (string $secret, string $key) => [$key, $secret])->values()->toArray()
            );
        }

        // Static variables
        if (! empty($data['env_static'])) {
            $hasAny = true;
            info('Static variables (fixed values):');
            table(
                ['ENV Variable', 'Value'],
                collect($data['env_static'])->map(fn (string $value, string $key) => [$key, $value])->values()->toArray()
            );
        }

        // Build env variables
        if (! empty($data['build_env'])) {
            $hasAny = true;
            info('Build variables (available during asset build):');
            table(
                ['ENV Variable', 'Value'],
                collect($data['build_env'])->map(fn (string $value, string $key) => [$key, $value])->values()->toArray()
            );
        }

        // Custom commands
        if (! empty($data['custom_commands'])) {
            $hasAny = true;
            info('Custom post-deploy commands:');
            table(
                ['Command'],
                collect($data['custom_commands'])->map(fn (string $cmd) => [$cmd])->toArray()
            );
        }

        // Notifications
        $webhookSecret = $data['notifications']['slack_webhook_secret'] ?? '';
        if ($webhookSecret !== '') {
            $hasAny = true;
            info('Notifications:');
            table(
                ['Type', 'Secret'],
                [['Slack webhook', $webhookSecret]]
            );
        }

        if (! $hasAny) {
            warning('No custom environment variables configured.');
            info('Run "php artisan netsons:env add" to add variables.');
        }

        return self::SUCCESS;
    }

    protected function addVariable(): int
    {
        $type = select('What type of variable?', [
            'secret' => 'Secret-backed (from GitHub Secrets)',
            'static' => 'Static (fixed value)',
            'build' => 'Build (available during asset build)',
        ]);

        match ($type) {
            'secret' => $this->addSecretBacked(),
            'static' => $this->addStatic(),
            'build' => $this->addBuild(),
        };

        info('Variable added to netsons-deploy.json.');
        note('Run "php artisan netsons:install --force" to regenerate the workflow.');

        return self::SUCCESS;
    }

    protected function addSecretBacked(): void
    {
        $envKey = text('ENV variable name');
        $secretName = text(
            label: 'GitHub Secret name (default: same as ENV name)',
            default: $envKey,
        );

        $this->configManager->addEnvMapping($envKey, $secretName);
    }

    protected function addStatic(): void
    {
        $envKey = text('ENV variable name');
        $value = text('Value');

        $this->configManager->addEnvStatic($envKey, $value);
    }

    protected function addBuild(): void
    {
        $envKey = text('ENV variable name');
        $value = text('Value');

        $this->configManager->addBuildEnv($envKey, $value);
    }

    protected function removeVariable(): int
    {
        $data = $this->configManager->read();
        $choices = [];

        foreach ($data['env_mapping'] as $key => $secret) {
            $choices["{$key} (secret: {$secret})"] = "{$key} (secret: {$secret})";
        }
        foreach ($data['env_static'] as $key => $value) {
            $choices["{$key} (static: {$value})"] = "{$key} (static: {$value})";
        }
        foreach ($data['build_env'] as $key => $value) {
            $choices["{$key} (build: {$value})"] = "{$key} (build: {$value})";
        }

        if (empty($choices)) {
            warning('No variables configured to remove.');

            return self::SUCCESS;
        }

        $selected = select('Which variable to remove?', $choices);

        // Parse the selection to determine type and key
        if (preg_match('/^(\S+)\s+\(secret:/', $selected, $matches)) {
            $this->configManager->removeEnvMapping($matches[1]);
        } elseif (preg_match('/^(\S+)\s+\(static:/', $selected, $matches)) {
            $this->configManager->removeEnvStatic($matches[1]);
        } elseif (preg_match('/^(\S+)\s+\(build:/', $selected, $matches)) {
            $this->configManager->removeBuildEnv($matches[1]);
        }

        info('Variable removed from netsons-deploy.json.');
        note('Run "php artisan netsons:install --force" to regenerate the workflow.');

        return self::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        error("Unknown action: {$action}. Use list, add, or remove.");

        return self::FAILURE;
    }
}
