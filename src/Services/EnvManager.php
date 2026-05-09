<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Services;

class EnvManager
{
    public function escapeForSed(string $value): string
    {
        // Escape characters that are special in sed replacement strings
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('/', '\\/', $value);
        $value = str_replace('&', '\\&', $value);
        $value = str_replace('"', '\\"', $value);

        return $value;
    }

    public function generateSedCommands(array $mapping): string
    {
        if (empty($mapping)) {
            return '';
        }

        $commands = [];

        foreach ($mapping as $key => $value) {
            $escapedValue = $this->escapeForSed($value);
            $commands[] = "sed -i \"s/^{$key}=.*/{$key}={$escapedValue}/\" .env";
        }

        return implode("\n", $commands);
    }
}
