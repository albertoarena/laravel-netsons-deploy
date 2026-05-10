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
        'notifications' => [],
    ];

    public function __construct(
        private readonly string $jsonPath,
    ) {}

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

    public function removeCustomCommand(string $command): void
    {
        $data = $this->read();
        $data['custom_commands'] = array_values(
            array_filter($data['custom_commands'], fn (string $c) => $c !== $command)
        );
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
