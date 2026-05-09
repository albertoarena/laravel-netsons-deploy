<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Strategies\FtpStrategy;
use AlbertoArena\NetsonsDeploy\Strategies\GitStrategy;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'netsons:install
                            {--strategy= : Deployment strategy (ftp or git)}';

    protected $description = 'Install and configure Netsons Deploy for your Laravel application';

    public function handle(): int
    {
        $this->info('');
        $this->info('  Netsons Deploy — Installation');
        $this->info('  =============================');
        $this->info('');

        $strategy = $this->option('strategy')
            ?? ($this->input->isInteractive()
                ? $this->choice('Which deployment strategy?', ['ftp', 'git'], 0)
                : 'ftp');

        $this->publishConfig();
        $this->showRequiredSecrets($strategy);
        $this->showRequiredVariables($strategy);
        $this->showNextSteps($strategy);

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'netsons-deploy-config',
            '--force' => true,
        ]);

        $this->info('  Config published to config/netsons-deploy.php');
    }

    protected function showRequiredSecrets(string $strategy): void
    {
        $strategyInstance = $strategy === 'git' ? new GitStrategy() : new FtpStrategy();

        $this->info('');
        $this->info('  Required GitHub Secrets:');
        $this->table(
            ['Secret', 'Description'],
            collect($strategyInstance->requiredSecrets())->map(fn (string $secret) => [
                $secret,
                $this->getSecretDescription($secret),
            ])->toArray()
        );
    }

    protected function showRequiredVariables(string $strategy): void
    {
        $strategyInstance = $strategy === 'git' ? new GitStrategy() : new FtpStrategy();

        $this->info('');
        $this->info('  Required GitHub Variables:');
        $this->table(
            ['Variable', 'Description'],
            collect($strategyInstance->requiredVariables())->map(fn (string $variable) => [
                $variable,
                $this->getVariableDescription($variable),
            ])->toArray()
        );
    }

    protected function showNextSteps(string $strategy): void
    {
        $this->info('');
        $this->info('  Next steps:');
        $this->info('  1. Edit config/netsons-deploy.php with your settings');
        $this->info('  2. Add the required secrets to your GitHub repository');
        $this->info('  3. Add the required variables to your GitHub repository');
        $this->info("  4. Set up your deployment workflow using the '{$strategy}' strategy");
        $this->info('');
    }

    protected function getSecretDescription(string $secret): string
    {
        return match ($secret) {
            'SSH_PRIVATE_KEY' => 'SSH private key for server access',
            'SSH_KNOWN_HOSTS' => 'SSH known hosts entry for the server',
            'FTP_HOST' => 'FTP server hostname',
            'FTP_USER' => 'FTP username',
            'FTP_PASS' => 'FTP password',
            'FTP_PORT' => 'FTP port (default: 21)',
            default => '',
        };
    }

    protected function getVariableDescription(string $variable): string
    {
        return match ($variable) {
            'DEPLOY_PATH' => 'Remote deploy path (e.g. public_html)',
            'APP_ENV' => 'Application environment (e.g. production)',
            'APP_DEBUG' => 'Debug mode (true/false)',
            'APP_URL' => 'Application URL',
            'GIT_REPO' => 'Git repository URL (e.g. git@github.com:user/repo.git)',
            'GIT_BRANCH' => 'Git branch to deploy (e.g. main)',
            default => '',
        };
    }
}
