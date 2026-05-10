<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Strategies\FtpStrategy;
use AlbertoArena\NetsonsDeploy\Strategies\GitStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckCommand extends Command
{
    protected $signature = 'netsons:check';

    protected $description = 'Validate Netsons Deploy configuration and readiness';

    public function handle(): int
    {
        $this->info('');
        $this->info('  Netsons Deploy — Configuration Check');
        $this->info('  ====================================');
        $this->info('');

        $config = config('netsons-deploy');
        $strategy = $config['strategy'] ?? 'ftp';
        $strategyInstance = $strategy === 'git' ? new GitStrategy() : new FtpStrategy();

        $this->showConfiguration($config, $strategy);
        $this->showWorkflowStatus();
        $this->showSecrets($strategyInstance);
        $this->showVariables($strategyInstance);
        $this->showValidation($strategyInstance, $config);

        return self::SUCCESS;
    }

    protected function showConfiguration(array $config, string $strategy): void
    {
        $this->info('  Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Strategy', $strategy],
                ['SSH Port', (string) ($config['ssh']['port'] ?? 65100)],
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

        $this->info('');
        $this->info('  Workflow File:');

        if ($exists) {
            $this->info('    .github/workflows/deploy.yml — found');
        } else {
            $this->warn('    .github/workflows/deploy.yml — not found');
            $this->info('    Run "php artisan netsons:install" to generate it.');
        }
    }

    protected function showSecrets(FtpStrategy|GitStrategy $strategy): void
    {
        $this->info('');
        $this->info('  Required GitHub Secrets:');
        $this->table(
            ['Secret'],
            collect($strategy->requiredSecrets())->map(fn (string $s) => [$s])->toArray()
        );
    }

    protected function showVariables(FtpStrategy|GitStrategy $strategy): void
    {
        $this->info('');
        $this->info('  Required GitHub Variables:');
        $this->table(
            ['Variable'],
            collect($strategy->requiredVariables())->map(fn (string $v) => [$v])->toArray()
        );
    }

    protected function showValidation(FtpStrategy|GitStrategy $strategy, array $config): void
    {
        $errors = $strategy->validate($config);

        $this->info('');
        if (empty($errors)) {
            $this->info('  Local config: All checks passed.');
        } else {
            $this->warn('  Validation Issues:');
            foreach ($errors as $error) {
                $this->warn("    - {$error}");
            }
        }

        $this->info('');
        $this->info('  Credentials (SSH, FTP, Git) are configured via GitHub Secrets/Variables,');
        $this->info('  not in local config. See: https://albertoarena.github.io/laravel-netsons-deploy/getting-started/github-secrets/');
        $this->info('');
    }
}
