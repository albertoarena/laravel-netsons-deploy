<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Services;

class DeployConfigManager
{
    private const DEFAULTS = [
        'env_mapping' => [],
        'env_static' => [],
        'build_env' => [],
        'custom_commands' => [],
        'seeders' => [],
        'notifications' => [],
        'envaudit' => false,
    ];

    public function __construct(
        private readonly string $jsonPath,
    ) {}

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public function exists(): bool
    {
        return file_exists($this->jsonPath);
    }

    public function read(): array
    {
        if (! $this->exists()) {
            return self::DEFAULTS;
        }

        $content = file_get_contents($this->jsonPath);
        $data = json_decode($content, true) ?? [];

        return array_merge(self::DEFAULTS, $data);
    }

    public function write(array $data): void
    {
        file_put_contents(
            $this->jsonPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    /**
     * Detect suggested seeders based on installed packages.
     *
     * Always includes DatabaseSeeder. Adds package-specific seeders
     * when known packages (e.g. spatie/laravel-permission) are detected
     * in composer.json require or require-dev.
     *
     * @return list<string>
     */
    public static function detectSeeders(string $composerJsonPath): array
    {
        $seeders = ['DatabaseSeeder'];

        if (! file_exists($composerJsonPath)) {
            return $seeders;
        }

        $content = file_get_contents($composerJsonPath);
        $composer = json_decode($content, true) ?? [];

        $packages = array_merge(
            array_keys($composer['require'] ?? []),
            array_keys($composer['require-dev'] ?? []),
        );

        if (in_array('spatie/laravel-permission', $packages, true)) {
            $seeders[] = 'RoleSeeder';
            $seeders[] = 'PermissionSeeder';
        }

        return $seeders;
    }

    /**
     * Parse .env.example and categorize variables into secret-backed, static, and build suggestions.
     *
     * @return array{secret: list<string>, static: array<string, string>, build: array<string, string>}
     */
    public static function parseEnvExample(string $envExamplePath): array
    {
        if (! file_exists($envExamplePath)) {
            return ['secret' => [], 'static' => [], 'build' => []];
        }

        $secretKeys = [];
        $staticKeys = [];
        $buildKeys = [];

        // Keys that are secret-backed (credentials, passwords, keys)
        $secretPatterns = [
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'MAIL_USERNAME', 'MAIL_PASSWORD',
            'REDIS_PASSWORD',
            'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
        ];

        // Keys already handled by the workflow, as secrets, or internal
        $excludePrefixes = [
            'APP_', 'DB_', 'MAIL_', 'REDIS_', 'AWS_',
            'LOG_', 'BROADCAST_', 'FILESYSTEM_', 'QUEUE_', 'CACHE_',
        ];

        // Values that indicate "not configured" — skip for static suggestions
        $placeholderValues = ['', 'null', 'true', 'false', '127.0.0.1', 'localhost'];

        $lines = file($envExamplePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Check if it's a known secret key (before prefix exclusion)
            if (in_array($key, $secretPatterns, true)) {
                $secretKeys[] = $key;

                continue;
            }

            // Skip excluded prefixes
            $excluded = false;
            foreach ($excludePrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            // Skip placeholder values
            if (in_array(strtolower($value), $placeholderValues, true)) {
                continue;
            }

            // Skip numeric-only values (ports, timeouts)
            if (ctype_digit($value)) {
                continue;
            }

            // VITE_* goes to build (for yarn build) — not static
            if (str_starts_with($key, 'VITE_')) {
                $buildKeys[$key] = $value;

                continue;
            }

            // Detect as static candidate
            $staticKeys[$key] = $value;
        }

        return ['secret' => $secretKeys, 'static' => $staticKeys, 'build' => $buildKeys];
    }

    public function has(string $section, string $key): bool
    {
        $data = $this->read();

        if (! isset($data[$section]) || ! is_array($data[$section])) {
            return false;
        }

        return array_key_exists($key, $data[$section]);
    }

    public function addEnvMapping(string $key, string $secretName): void
    {
        $data = $this->read();
        $data['env_mapping'][$key] = $secretName;
        $this->write($data);
    }

    public function addEnvStatic(string $key, string $value): void
    {
        $data = $this->read();
        $data['env_static'][$key] = $value;
        $this->write($data);
    }

    public function addBuildEnv(string $key, string $value): void
    {
        $data = $this->read();
        $data['build_env'][$key] = $value;
        $this->write($data);
    }

    public function addCustomCommand(string $command): void
    {
        $data = $this->read();

        if (! in_array($command, $data['custom_commands'], true)) {
            $data['custom_commands'][] = $command;
        }

        $this->write($data);
    }

    public function removeEnvMapping(string $key): void
    {
        $data = $this->read();
        unset($data['env_mapping'][$key]);
        $this->write($data);
    }

    public function removeEnvStatic(string $key): void
    {
        $data = $this->read();
        unset($data['env_static'][$key]);
        $this->write($data);
    }

    public function removeBuildEnv(string $key): void
    {
        $data = $this->read();
        unset($data['build_env'][$key]);
        $this->write($data);
    }

    public function addSeeder(string $seeder): void
    {
        $data = $this->read();

        if (! in_array($seeder, $data['seeders'], true)) {
            $data['seeders'][] = $seeder;
        }

        $this->write($data);
    }

    public function removeSeeder(string $seeder): void
    {
        $data = $this->read();
        $data['seeders'] = array_values(
            array_filter($data['seeders'], fn (string $s) => $s !== $seeder)
        );
        $this->write($data);
    }

    public function removeCustomCommand(string $command): void
    {
        $data = $this->read();
        $data['custom_commands'] = array_values(
            array_filter($data['custom_commands'], fn (string $c) => $c !== $command)
        );
        $this->write($data);
    }

    public function setEnvaudit(bool $enabled): void
    {
        $data = $this->read();
        $data['envaudit'] = $enabled;
        $this->write($data);
    }

    public function setSlackWebhook(?string $secretName): void
    {
        $data = $this->read();

        if ($secretName === null) {
            $data['notifications'] = [];
        } else {
            $data['notifications'] = ['slack_webhook_secret' => $secretName];
        }

        $this->write($data);
    }
}
