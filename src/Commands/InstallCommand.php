<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Services\DeployConfigManager;
use AlbertoArena\NetsonsDeploy\Services\EnvManager;
use AlbertoArena\NetsonsDeploy\Strategies\FtpStrategy;
use AlbertoArena\NetsonsDeploy\Strategies\GitStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'netsons:install
                            {--strategy= : Deployment strategy (ftp or git)}
                            {--force : Overwrite existing config file}';

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

        $configExists = File::exists(config_path('netsons-deploy.php'));

        if ($configExists) {
            if (! $this->shouldOverwrite()) {
                $this->info('  Keeping existing config. Only updating displayed information.');
                $this->publishWorkflow($strategy);
                $this->showRequiredSecrets($strategy);
                $this->showRequiredVariables($strategy);
                $this->showNextSteps($strategy);

                return self::SUCCESS;
            }

            $this->info('  Overwriting existing config file...');
        }

        $this->publishConfig($strategy);
        $this->collectDeployJson();
        $this->publishWorkflow($strategy);
        $this->showRequiredSecrets($strategy);
        $this->showRequiredVariables($strategy);
        $this->showNextSteps($strategy);

        return self::SUCCESS;
    }

    protected function shouldOverwrite(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm('  Config file already exists. Overwrite?', false);
    }

    protected function publishConfig(string $strategy): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'netsons-deploy-config',
            '--force' => true,
        ]);

        $this->updateConfigStrategy($strategy);

        $this->info('  Config published to config/netsons-deploy.php');
    }

    protected function updateConfigStrategy(string $strategy): void
    {
        $configPath = config_path('netsons-deploy.php');

        if (! File::exists($configPath)) {
            return;
        }

        $contents = File::get($configPath);

        // Replace the strategy default in env() call
        $contents = preg_replace(
            "/('strategy'\s*=>\s*env\(\s*'NETSONS_DEPLOY_STRATEGY',\s*')[^']*('\s*\))/",
            '${1}'.$strategy.'${2}',
            $contents
        );

        File::put($configPath, $contents);
    }

    protected function collectDeployJson(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $jsonPath = base_path('netsons-deploy.json');
        $manager = new DeployConfigManager($jsonPath);

        // Skip if JSON already exists and user doesn't want to reconfigure
        if ($manager->exists()) {
            if (! $this->confirm('  netsons-deploy.json already exists. Reconfigure?', false)) {
                return;
            }
        }

        $this->info('');
        $this->info('  Environment variable setup');
        $this->info('  -------------------------');

        // Secret-backed env vars
        if ($this->confirm('  Add secret-backed .env variables (from GitHub Secrets)?', false)) {
            $this->collectEnvMappings($manager);
        }

        // Static env vars
        if ($this->confirm('  Add static .env variables (fixed values)?', false)) {
            $this->collectEnvStatic($manager);
        }

        // Build env vars
        if ($this->confirm('  Add build environment variables (e.g., VITE_APP_NAME)?', false)) {
            $this->collectBuildEnv($manager);
        }

        // Custom commands
        if ($this->confirm('  Add custom post-deploy artisan commands?', false)) {
            $this->collectCustomCommands($manager);
        }

        // Slack notifications
        if ($this->confirm('  Enable Slack deploy notifications?', false)) {
            $secretName = $this->ask('  GitHub Secret name for Slack webhook URL', 'SLACK_WEBHOOK_DEBUG');
            $manager->setSlackWebhook($secretName);
        }

        $this->info('  Configuration saved to netsons-deploy.json');
    }

    protected function collectEnvMappings(DeployConfigManager $manager): void
    {
        do {
            $envKey = $this->ask('  ENV variable name (e.g., DB_PASSWORD)');

            if ($envKey === null || $envKey === '') {
                break;
            }

            $secretName = $this->ask('  GitHub Secret name', $envKey);
            $manager->addEnvMapping($envKey, $secretName);
            $this->info("    Added: {$envKey} -> secrets.{$secretName}");
        } while ($this->confirm('  Add another secret-backed variable?', false));
    }

    protected function collectEnvStatic(DeployConfigManager $manager): void
    {
        do {
            $envKey = $this->ask('  ENV variable name (e.g., SESSION_DRIVER)');

            if ($envKey === null || $envKey === '') {
                break;
            }

            $value = $this->ask('  Value');
            $manager->addEnvStatic($envKey, $value);
            $this->info("    Added: {$envKey}={$value}");
        } while ($this->confirm('  Add another static variable?', false));
    }

    protected function collectBuildEnv(DeployConfigManager $manager): void
    {
        do {
            $envKey = $this->ask('  ENV variable name (e.g., VITE_APP_NAME)');

            if ($envKey === null || $envKey === '') {
                break;
            }

            $value = $this->ask('  Value');
            $manager->addBuildEnv($envKey, $value);
            $this->info("    Added: {$envKey}={$value}");
        } while ($this->confirm('  Add another build variable?', false));
    }

    protected function collectCustomCommands(DeployConfigManager $manager): void
    {
        $this->info('  Common commands:');
        $this->info('    - event-sourcing:cache-event-handlers 2>/dev/null || true');
        $this->info('    - permission:cache-reset');
        $this->info('    - horizon:terminate');

        do {
            $command = $this->ask('  Artisan command (without "artisan" prefix)');

            if ($command === null || $command === '') {
                break;
            }

            $manager->addCustomCommand($command);
            $this->info("    Added: artisan {$command}");
        } while ($this->confirm('  Add another command?', false));
    }

    protected function publishWorkflow(string $strategy): void
    {
        $workflowPath = base_path('.github/workflows/deploy.yml');
        $stubPath = __DIR__.'/../../stubs/workflows/deploy.yml.stub';

        if (File::exists($workflowPath) && ! $this->option('force')) {
            $this->info('  Workflow .github/workflows/deploy.yml already exists (use --force to overwrite).');

            return;
        }

        if (! File::exists($stubPath)) {
            $this->warn('  Workflow stub not found.');

            return;
        }

        File::ensureDirectoryExists(dirname($workflowPath));

        $config = config('netsons-deploy') ?? [];
        $jsonPath = base_path('netsons-deploy.json');
        $deployConfig = (new DeployConfigManager($jsonPath))->read();
        $envManager = new EnvManager();

        $contents = File::get($stubPath);

        // Replace simple placeholders
        $contents = str_replace('%%STRATEGY%%', $strategy, $contents);
        $contents = str_replace('%%PHP_VERSION%%', $this->resolvePhpVersion($config), $contents);
        $contents = str_replace('%%NODE_VERSION%%', '22', $contents);
        $contents = str_replace('%%PACKAGE_MANAGER%%', 'yarn', $contents);
        $contents = str_replace('%%REMOTE_PHP%%', $config['php_binary'] ?? '/usr/local/bin/ea-php84', $contents);
        $contents = str_replace('%%RELEASES_KEEP%%', (string) ($config['releases']['keep'] ?? 5), $contents);

        // FTP server-dir (W9)
        $ftpRootPath = $config['ftp']['root_path'] ?? '';
        $contents = str_replace('%%FTP_SERVER_DIR%%', $this->resolveFtpServerDir($ftpRootPath), $contents);

        // Build env (W7)
        $contents = str_replace("%%BUILD_ENV%%", $this->generateBuildEnvBlock($deployConfig['build_env']), $contents);

        // Env mapping env block + sed block (W2)
        $contents = str_replace(
            "%%ENV_MAPPING_ENV_BLOCK%%",
            $this->generateEnvMappingEnvBlock($deployConfig['env_mapping'], $envManager),
            $contents
        );
        $contents = str_replace(
            "%%ENV_MAPPING_SED_BLOCK%%",
            $this->generateEnvMappingSedBlock($deployConfig['env_mapping'], $deployConfig['env_static'], $envManager),
            $contents
        );

        // Seeders (W4)
        $seeders = $config['seeders'] ?? [];
        $contents = str_replace("%%SEEDERS%%", $this->generateSeedersBlock($seeders), $contents);

        // Custom commands (W6)
        $contents = str_replace(
            "%%CUSTOM_COMMANDS%%",
            $this->generateCustomCommandsBlock($deployConfig['custom_commands']),
            $contents
        );

        // Notifications (W8)
        $contents = str_replace(
            "%%NOTIFICATIONS%%",
            $this->generateNotificationsBlock($deployConfig['notifications']),
            $contents
        );

        // Remove the placeholder instruction comment — no longer needed
        $contents = preg_replace('/^#\s*1\.\s*Replace all.*\n/m', '', $contents);

        File::put($workflowPath, $contents);

        $this->info('  Workflow published to .github/workflows/deploy.yml');
    }

    protected function resolvePhpVersion(array $config): string
    {
        $phpBinary = $config['php_binary'] ?? '/usr/local/bin/ea-php84';

        // Extract version from ea-phpXX path (e.g. ea-php84 -> 8.4)
        if (preg_match('/ea-php(\d)(\d)/', $phpBinary, $matches)) {
            return $matches[1].'.'.$matches[2];
        }

        return '8.4';
    }

    protected function resolveFtpServerDir(string $ftpRootPath): string
    {
        if ($ftpRootPath === '') {
            return '${{ vars.DEPLOY_PATH }}/releases/${{ steps.release.outputs.dir }}/';
        }

        return 'releases/${{ steps.release.outputs.dir }}/';
    }

    protected function generateBuildEnvBlock(array $buildEnv): string
    {
        if (empty($buildEnv)) {
            return '';
        }

        $lines = ['        env:'];
        foreach ($buildEnv as $key => $value) {
            $lines[] = "          {$key}: \"{$value}\"";
        }

        return implode("\n", $lines)."\n";
    }

    protected function generateEnvMappingEnvBlock(array $envMapping, EnvManager $envManager): string
    {
        if (empty($envMapping)) {
            return '';
        }

        $block = $envManager->generateWorkflowEnvBlock($envMapping, '          ');

        return $block."\n";
    }

    protected function generateEnvMappingSedBlock(array $envMapping, array $envStatic, EnvManager $envManager): string
    {
        if (empty($envMapping) && empty($envStatic)) {
            return '';
        }

        $block = $envManager->generateWorkflowSedBlock($envMapping, $envStatic, '            ');

        return $block."\n";
    }

    protected function generateSeedersBlock(array $seeders): string
    {
        if (empty($seeders)) {
            return '';
        }

        $lines = [];
        foreach ($seeders as $seeder) {
            $lines[] = "              \${{ env.REMOTE_PHP }} artisan db:seed --class={$seeder} --force";
        }

        return implode("\n", $lines)."\n";
    }

    protected function generateCustomCommandsBlock(array $customCommands): string
    {
        if (empty($customCommands)) {
            return '';
        }

        $lines = [];
        foreach ($customCommands as $command) {
            $lines[] = "            \${{ env.REMOTE_PHP }} artisan {$command}";
        }

        return implode("\n", $lines)."\n";
    }

    protected function generateNotificationsBlock(array $notifications): string
    {
        $webhookSecret = $notifications['slack_webhook_secret'] ?? '';

        if ($webhookSecret === '') {
            return '';
        }

        return <<<YAML
      # ── Notifications ─────────────────────────────────────────────────────
      - name: Notify Slack on success
        if: success()
        env:
          SLACK_WEBHOOK: \${{ secrets.{$webhookSecret} }}
        run: |
          if [ -n "\$SLACK_WEBHOOK" ]; then
            curl -s -X POST -H 'Content-type: application/json' \\
              --data '{"text":":white_check_mark: Deploy \${{ github.event.inputs.environment }} succeeded — release \${{ steps.release.outputs.dir }}"}' \\
              "\$SLACK_WEBHOOK"
          fi

      - name: Notify Slack on failure
        if: failure()
        env:
          SLACK_WEBHOOK: \${{ secrets.{$webhookSecret} }}
        run: |
          if [ -n "\$SLACK_WEBHOOK" ]; then
            curl -s -X POST -H 'Content-type: application/json' \\
              --data '{"text":":x: Deploy \${{ github.event.inputs.environment }} failed — \${{ github.server_url }}/\${{ github.repository }}/actions/runs/\${{ github.run_id }}"}' \\
              "\$SLACK_WEBHOOK"
          fi

YAML;
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
        $this->info('  1. Review .github/workflows/deploy.yml and adjust settings');
        $this->info('  2. Add the required secrets to your GitHub repository');
        $this->info('  3. Add the required variables to your GitHub repository');
        $this->info('  4. Push to GitHub and trigger the workflow');
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
