<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Commands;

use AlbertoArena\NetsonsDeploy\Services\DeployConfigManager;
use AlbertoArena\NetsonsDeploy\Services\EnvManager;
use AlbertoArena\NetsonsDeploy\Strategies\FtpStrategy;
use AlbertoArena\NetsonsDeploy\Strategies\GitStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'netsons:install
                            {--strategy= : Deployment strategy (ftp or git)}
                            {--force : Overwrite existing config file}';

    protected $description = 'Install and configure Netsons Deploy for your Laravel application';

    public function handle(): int
    {
        note('Netsons Deploy — Installation');

        $strategy = $this->option('strategy')
            ?? ($this->input->isInteractive()
                ? select('Which deployment strategy?', ['ftp', 'git'], 'ftp')
                : 'ftp');

        $configExists = File::exists(config_path('netsons-deploy.php'));

        if ($configExists) {
            if (! $this->shouldOverwrite()) {
                info('Keeping existing config. Only updating displayed information.');
                $this->publishWorkflow($strategy);
                $this->showRequiredSecrets($strategy);
                $this->showRequiredVariables($strategy);
                $this->showNextSteps();

                return self::SUCCESS;
            }

            info('Overwriting existing config file...');
        }

        $this->publishConfig($strategy);
        $this->collectDeployJson($strategy);
        $this->publishWorkflow($strategy);
        $this->showRequiredSecrets($strategy);
        $this->showRequiredVariables($strategy);
        $this->showNextSteps();

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

        return confirm('Config file already exists. Overwrite?', false);
    }

    protected function publishConfig(string $strategy): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'netsons-deploy-config',
            '--force' => true,
        ]);

        $this->updateConfigStrategy($strategy);

        info('Config published to config/netsons-deploy.php');
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

    protected function collectDeployJson(string $strategy): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        $jsonPath = base_path('netsons-deploy.json');
        $manager = new DeployConfigManager($jsonPath);

        // Skip if JSON already exists and user doesn't want to reconfigure
        $existingConfig = [];
        if ($manager->exists()) {
            if (! confirm('netsons-deploy.json already exists. Reconfigure?', false)) {
                return;
            }

            // Preserve existing values to use as defaults during reconfiguration
            $existingConfig = $manager->read();
            $manager->write($manager::defaults());
        }

        note('Environment variable setup');

        // Show already-handled strategy secrets
        $strategyInstance = $strategy === 'git' ? new GitStrategy : new FtpStrategy;
        $strategySecrets = $strategyInstance->requiredSecrets();
        note("The following secrets are already handled by the {$strategy} strategy:\n  ".implode(', ', $strategySecrets));

        // Auto-detect env vars from .env.example
        $envExamplePath = base_path('.env.example');
        $detected = DeployConfigManager::parseEnvExample($envExamplePath);

        if (! empty($detected['secret']) || ! empty($detected['static']) || ! empty($detected['build'])) {
            $this->collectDetectedEnvVars($manager, $detected, $existingConfig);
        } else {
            // Fallback to manual entry if no .env.example or no detected vars
            if (confirm('Add additional .env variables from GitHub Secrets? (e.g., DB_PASSWORD, DB_USERNAME)', false)) {
                $this->collectEnvMappings($manager);
            }

            if (confirm('Add static .env variables (fixed values)?', false)) {
                $this->collectEnvStatic($manager);
            }
        }

        // Build env vars
        if (confirm('Add build environment variables (e.g., VITE_APP_NAME)?', false)) {
            $this->collectBuildEnv($manager);
        }

        // Restore existing custom commands, then offer to modify
        $existingCommands = $existingConfig['custom_commands'] ?? [];
        foreach ($existingCommands as $cmd) {
            $manager->addCustomCommand($cmd);
        }
        if (! empty($existingCommands)) {
            info('Restored '.count($existingCommands).' custom command(s) from previous config.');
        }
        if (confirm('Add custom post-deploy artisan commands?', false)) {
            $this->collectCustomCommands($manager);
        }

        // Slack notifications — use existing value as default
        $existingWebhook = $existingConfig['notifications']['slack_webhook_secret'] ?? '';
        if ($existingWebhook !== '') {
            $manager->setSlackWebhook($existingWebhook);
            info("Restored Slack notification (secret: {$existingWebhook}).");
        } elseif (confirm('Enable Slack deploy notifications?', false)) {
            $secretName = text(
                label: 'GitHub Secret name for Slack webhook URL',
                default: 'SLACK_WEBHOOK_DEBUG',
            );
            $manager->setSlackWebhook($secretName);
        }

        // Envaudit — use existing value as default
        $existingEnvaudit = $existingConfig['envaudit'] ?? true;
        $enableEnvaudit = confirm(
            label: 'Enable envaudit .env validation after deploy?',
            default: $existingEnvaudit,
            hint: 'See: https://albertoarena.github.io/envaudit/getting-started/ci-integration/',
        );
        $manager->setEnvaudit($enableEnvaudit);

        info('Configuration saved to netsons-deploy.json');
    }

    protected function collectDetectedEnvVars(DeployConfigManager $manager, array $detected, array $existingConfig = []): void
    {
        // Secret-backed variables
        if (! empty($detected['secret'])) {
            $options = [];
            foreach ($detected['secret'] as $key) {
                $options[$key] = $key;
            }

            $selected = multiselect(
                label: 'Select secret-backed .env variables (from GitHub Secrets)',
                options: $options,
                default: array_keys($options),
                hint: 'Space to toggle, Enter to confirm',
            );

            foreach ($selected as $key) {
                $existingSecret = $existingConfig['env_mapping'][$key] ?? $key;
                $manager->addEnvMapping($key, $existingSecret);
            }

            if (! empty($selected)) {
                info('Added '.count($selected).' secret-backed variable(s).');
            }
        }

        // Static variables
        if (! empty($detected['static'])) {
            $options = [];
            foreach ($detected['static'] as $key => $value) {
                // Use existing value if available, otherwise .env.example value
                $displayValue = $existingConfig['env_static'][$key] ?? $value;
                $options[$key] = "{$key} = {$displayValue}";
            }

            $selected = multiselect(
                label: 'Select static .env variables (fixed values)',
                options: $options,
                default: array_keys($options),
                hint: 'Space to toggle, Enter to confirm',
            );

            // Let the user edit values for selected items
            foreach ($selected as $key) {
                // Prefer existing config value over .env.example default
                $currentValue = $existingConfig['env_static'][$key] ?? $detected['static'][$key];
                $value = text(
                    label: "{$key}",
                    default: $currentValue,
                    hint: 'Edit value or press Enter to keep',
                );
                $manager->addEnvStatic($key, $value);
            }

            if (! empty($selected)) {
                info('Added '.count($selected).' static variable(s).');
            }
        }

        // Build variables (VITE_*)
        if (! empty($detected['build'])) {
            $options = [];
            foreach ($detected['build'] as $key => $value) {
                // Use existing value if available
                $displayValue = $existingConfig['build_env'][$key] ?? $value;
                $options[$key] = "{$key} = {$displayValue}";
            }

            $selected = multiselect(
                label: 'Select build variables (VITE_* for asset compilation)',
                options: $options,
                default: array_keys($options),
                hint: 'Space to toggle, Enter to confirm',
            );

            foreach ($selected as $key) {
                // Prefer existing config value over .env.example default
                $currentValue = $existingConfig['build_env'][$key] ?? $detected['build'][$key];
                $value = text(
                    label: "{$key}",
                    default: $currentValue,
                    hint: 'Edit value or press Enter to keep',
                );
                $manager->addBuildEnv($key, $value);
                // VITE_* also goes to env_static for server-side access
                $manager->addEnvStatic($key, $value);
            }

            if (! empty($selected)) {
                info('Added '.count($selected).' build variable(s) (also set in .env).');
            }
        }

        // Still allow manual additions
        if (confirm('Add more .env variables manually?', false)) {
            if (confirm('Add secret-backed variables?', false)) {
                $this->collectEnvMappings($manager);
            }
            if (confirm('Add static variables?', false)) {
                $this->collectEnvStatic($manager);
            }
        }
    }

    protected function collectEnvMappings(DeployConfigManager $manager): void
    {
        do {
            $envKey = text('ENV variable name (e.g., DB_PASSWORD)');

            if ($envKey === '') {
                break;
            }

            if ($manager->has('env_mapping', $envKey)) {
                warning("\"{$envKey}\" is already configured. Skipping.");
            } else {
                $secretName = text(
                    label: 'GitHub Secret name',
                    default: $envKey,
                );
                $manager->addEnvMapping($envKey, $secretName);
                info("Added: {$envKey} -> secrets.{$secretName}");
            }
        } while (confirm('Add another secret-backed variable?', false));
    }

    protected function collectEnvStatic(DeployConfigManager $manager): void
    {
        do {
            $envKey = text('ENV variable name (e.g., SESSION_DRIVER)');

            if ($envKey === '') {
                break;
            }

            if ($manager->has('env_static', $envKey)) {
                warning("\"{$envKey}\" is already configured. Skipping.");
            } else {
                $value = text('Value');
                $manager->addEnvStatic($envKey, $value);
                info("Added: {$envKey}={$value}");
            }
        } while (confirm('Add another static variable?', false));
    }

    protected function collectBuildEnv(DeployConfigManager $manager): void
    {
        do {
            $envKey = text('ENV variable name (e.g., VITE_APP_NAME)');

            if ($envKey === '') {
                break;
            }

            if ($manager->has('build_env', $envKey)) {
                warning("\"{$envKey}\" is already configured. Skipping.");
            } else {
                $value = text('Value');
                $manager->addBuildEnv($envKey, $value);
                info("Added: {$envKey}={$value}");
            }
        } while (confirm('Add another build variable?', false));
    }

    protected function collectCustomCommands(DeployConfigManager $manager): void
    {
        note("Common commands:\n  - event-sourcing:cache-event-handlers 2>/dev/null || true\n  - permission:cache-reset\n  - horizon:terminate");

        do {
            $command = text('Artisan command (without "artisan" prefix)');

            if ($command === '') {
                break;
            }

            $manager->addCustomCommand($command);
            info("Added: artisan {$command}");
        } while (confirm('Add another command?', false));
    }

    protected function publishWorkflow(string $strategy): void
    {
        $workflowPath = base_path('.github/workflows/deploy.yml');
        $stubPath = __DIR__.'/../../stubs/workflows/deploy.yml.stub';

        if (File::exists($workflowPath) && ! $this->option('force')) {
            info('Workflow .github/workflows/deploy.yml already exists (use --force to overwrite).');

            return;
        }

        if (! File::exists($stubPath)) {
            warning('Workflow stub not found.');

            return;
        }

        File::ensureDirectoryExists(dirname($workflowPath));

        $config = config('netsons-deploy') ?? [];
        $jsonPath = base_path('netsons-deploy.json');
        $deployConfig = (new DeployConfigManager($jsonPath))->read();
        $envManager = new EnvManager;

        $contents = File::get($stubPath);

        // Replace simple placeholders
        $contents = str_replace('%%STRATEGY%%', $strategy, $contents);
        $contents = str_replace('%%PHP_VERSION%%', $this->resolvePhpVersion($config), $contents);
        $contents = str_replace('%%NODE_VERSION%%', '22', $contents);
        $contents = str_replace('%%PACKAGE_MANAGER%%', 'yarn', $contents);
        $contents = str_replace('%%REMOTE_PHP%%', $config['php_binary'] ?? '/usr/local/bin/ea-php84', $contents);
        $contents = str_replace('%%REMOTE_COMPOSER%%', $config['composer_binary'] ?? '/opt/cpanel/composer/bin/composer', $contents);
        $contents = str_replace('%%RELEASES_KEEP%%', (string) ($config['releases']['keep'] ?? 5), $contents);

        // FTP server-dir (W9)
        $ftpRootPath = $config['ftp']['root_path'] ?? '';
        $contents = str_replace('%%FTP_SERVER_DIR%%', $this->resolveFtpServerDir($ftpRootPath), $contents);

        // Build env (W7)
        $contents = str_replace('%%BUILD_ENV%%', $this->generateBuildEnvBlock($deployConfig['build_env']), $contents);

        // Env mapping env block + sed block (W2)
        $contents = str_replace(
            '%%ENV_MAPPING_ENV_BLOCK%%',
            $this->generateEnvMappingEnvBlock($deployConfig['env_mapping'], $envManager),
            $contents
        );
        $contents = str_replace(
            '%%ENV_MAPPING_SED_BLOCK%%',
            $this->generateEnvMappingSedBlock($deployConfig['env_mapping'], $deployConfig['env_static'], $envManager),
            $contents
        );

        // Seeders (W4)
        $seeders = $config['seeders'] ?? [];
        $contents = str_replace('%%SEEDERS%%', $this->generateSeedersBlock($seeders), $contents);

        // Custom commands (W6)
        $contents = str_replace(
            '%%CUSTOM_COMMANDS%%',
            $this->generateCustomCommandsBlock($deployConfig['custom_commands']),
            $contents
        );

        // Envaudit (B6)
        $contents = str_replace(
            '%%ENVAUDIT%%',
            $this->generateEnvauditBlock($deployConfig['envaudit'] ?? false),
            $contents
        );

        // Notifications (W8)
        $contents = str_replace(
            '%%NOTIFICATIONS%%',
            $this->generateNotificationsBlock($deployConfig['notifications']),
            $contents
        );

        // Remove the placeholder instruction comment — no longer needed
        $contents = preg_replace('/^#\s*1\.\s*Replace all.*\n/m', '', $contents);

        File::put($workflowPath, $contents);

        info('Workflow published to .github/workflows/deploy.yml');
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

    protected function generateEnvauditBlock(bool $enabled): string
    {
        if (! $enabled) {
            return '';
        }

        return <<<'YAML'
      # ── Envaudit ────────────────────────────────────────────────────────
      - name: Validate .env with envaudit
        env:
          SSH_HOST: ${{ secrets.SSH_HOST }}
          SSH_PORT: ${{ secrets.SSH_PORT || '65100' }}
          SSH_USER: ${{ secrets.SSH_USER }}
          DEPLOY_PATH: ${{ vars.DEPLOY_PATH }}
        run: |
          scp -P ${SSH_PORT} \
            ${SSH_USER}@${SSH_HOST}:~/${{ vars.DEPLOY_PATH }}/shared/.env .env
          npx @albertoarena/envaudit check --ci --no-color
          rm .env

YAML;
    }

    protected function showRequiredSecrets(string $strategy): void
    {
        $strategyInstance = $strategy === 'git' ? new GitStrategy : new FtpStrategy;

        note('Required GitHub Secrets:');
        table(
            ['Secret', 'Description'],
            collect($strategyInstance->requiredSecrets())->map(fn (string $secret) => [
                $secret,
                $this->getSecretDescription($secret),
            ])->toArray()
        );
    }

    protected function showRequiredVariables(string $strategy): void
    {
        $strategyInstance = $strategy === 'git' ? new GitStrategy : new FtpStrategy;

        note('Required GitHub Variables:');
        table(
            ['Variable', 'Description'],
            collect($strategyInstance->requiredVariables())->map(fn (string $variable) => [
                $variable,
                $this->getVariableDescription($variable),
            ])->toArray()
        );
    }

    protected function showNextSteps(): void
    {
        note("Next steps:\n  1. Review .github/workflows/deploy.yml and adjust settings\n  2. Add the required secrets to your GitHub repository\n  3. Add the required variables to your GitHub repository\n  4. Push to GitHub and trigger the workflow");
    }

    protected function getSecretDescription(string $secret): string
    {
        return match ($secret) {
            'SSH_HOST' => 'SSH hostname',
            'SSH_USER' => 'SSH username',
            'SSH_PRIVATE_KEY' => 'SSH private key for server access',
            'SSH_KNOWN_HOSTS' => 'SSH known hosts entry for the server',
            'SSH_KEY_PASSPHRASE' => 'SSH key passphrase (if set)',
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
            'GIT_REPO' => 'Git repository HTTPS URL (e.g. https://github.com/user/repo.git)',
            'GIT_BRANCH' => 'Git branch to deploy (e.g. main)',
            default => '',
        };
    }
}
