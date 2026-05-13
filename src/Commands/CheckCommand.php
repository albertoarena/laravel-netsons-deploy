<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Services\DeployConfigManager;
use AlbertoArena\NetsonsDeploy\Strategies\FtpStrategy;
use AlbertoArena\NetsonsDeploy\Strategies\GitStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class CheckCommand extends Command
{
    protected $signature = 'netsons:check';

    protected $description = 'Validate Netsons Deploy configuration and readiness';

    public function handle(): int
    {
        note('Netsons Deploy — Configuration Check');

        $config = config('netsons-deploy');
        $strategy = $config['strategy'] ?? 'ftp';
        $strategyInstance = $strategy === 'git' ? new GitStrategy : new FtpStrategy;

        $this->showConfiguration($config, $strategy);
        $this->showWorkflowStatus();
        $this->showDeployJsonStatus();
        $this->showSecrets($strategyInstance);
        $this->showVariables($strategyInstance);
        $this->showValidation($strategyInstance, $config);

        return self::SUCCESS;
    }

    protected function showConfiguration(array $config, string $strategy): void
    {
        info('Configuration:');
        table(
            ['Setting', 'Value'],
            [
                ['Strategy', $strategy],
                ['SSH Port', (string) ($config['ssh']['port'] ?? 65100)],
                ['SSH Retries', (string) ($config['ssh']['retries'] ?? 3)],
                ['SSH Retry Delay', (string) ($config['ssh']['retry_delay'] ?? 10).'s'],
                ['SSH Connect Timeout', (string) ($config['ssh']['connect_timeout'] ?? 30).'s'],
                ['PHP Binary', $config['php_binary'] ?? '/usr/local/bin/ea-php84'],
                ['Deploy Path', $config['deploy_path'] ?? 'public_html'],
                ['Releases Keep', (string) ($config['releases']['keep'] ?? 5)],
            ]
        );
    }

    protected function showWorkflowStatus(): void
    {
        $workflowPath = base_path('.github/workflows/deploy.yml');
        $exists = File::exists($workflowPath);

        info('Workflow File:');

        if ($exists) {
            note('.github/workflows/deploy.yml — found');
        } else {
            warning('.github/workflows/deploy.yml — not found');
            note('Run "php artisan netsons:install" to generate it.');
        }
    }

    protected function showDeployJsonStatus(): void
    {
        $jsonPath = base_path('netsons-deploy.json');
        $manager = new DeployConfigManager($jsonPath);

        info('Deploy Config (netsons-deploy.json):');

        if (! $manager->exists()) {
            warning('netsons-deploy.json — not found');
            note('Run "php artisan netsons:env add" to configure environment variables.');

            return;
        }

        note('netsons-deploy.json — found');

        $data = $manager->read();

        $rows = [];

        if (! empty($data['env_mapping'])) {
            foreach ($data['env_mapping'] as $key => $secret) {
                $rows[] = ['Secret-backed', $key, "secrets.{$secret}"];
            }
        }

        if (! empty($data['env_static'])) {
            foreach ($data['env_static'] as $key => $value) {
                $rows[] = ['Static', $key, $value];
            }
        }

        if (! empty($data['build_env'])) {
            foreach ($data['build_env'] as $key => $value) {
                $rows[] = ['Build', $key, $value];
            }
        }

        if (! empty($data['custom_commands'])) {
            foreach ($data['custom_commands'] as $command) {
                $rows[] = ['Command', 'artisan '.$command, ''];
            }
        }

        $webhookSecret = $data['notifications']['slack_webhook_secret'] ?? '';
        if ($webhookSecret !== '') {
            $rows[] = ['Notification', 'Slack', "secrets.{$webhookSecret}"];
        }

        if (! empty($rows)) {
            table(['Type', 'Key', 'Value/Source'], $rows);
        }
    }

    protected function showSecrets(FtpStrategy|GitStrategy $strategy): void
    {
        info('Required GitHub Secrets:');
        table(
            ['Secret'],
            collect($strategy->requiredSecrets())->map(fn (string $s) => [$s])->toArray()
        );
    }

    protected function showVariables(FtpStrategy|GitStrategy $strategy): void
    {
        info('Required GitHub Variables:');
        table(
            ['Variable'],
            collect($strategy->requiredVariables())->map(fn (string $v) => [$v])->toArray()
        );
    }

    protected function showValidation(FtpStrategy|GitStrategy $strategy, array $config): void
    {
        $errors = $strategy->validate($config);

        if (empty($errors)) {
            info('Local config: All checks passed.');
        } else {
            warning('Validation Issues:');
            foreach ($errors as $error) {
                warning("  - {$error}");
            }
        }

        note("Credentials (SSH, FTP, Git) are configured via GitHub Secrets/Variables,\nnot in local config. See: https://albertoarena.github.io/laravel-netsons-deploy/getting-started/github-secrets/");
    }
}
