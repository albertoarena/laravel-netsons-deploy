<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Services;

class EnvManager
{
    /**
     * Escape a value for use in a sed replacement string with pipe delimiter.
     * Escapes: \ & | "
     * Does NOT escape / since we use | as sed delimiter.
     */
    public function escapeForSed(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('&', '\\&', $value);
        $value = str_replace('|', '\\|', $value);
        $value = str_replace('"', '\\"', $value);

        return $value;
    }

    /**
     * Generate sed commands for static key-value pairs (values known at generation time).
     */
    public function generateSedCommands(array $mapping): string
    {
        if (empty($mapping)) {
            return '';
        }

        $commands = [];

        foreach ($mapping as $key => $value) {
            $escapedValue = $this->escapeForSed($value);
            $commands[] = "sed -i \"s|^{$key}=.*|{$key}={$escapedValue}|\" .env";
        }

        return implode("\n", $commands);
    }

    /**
     * Generate workflow YAML env block lines for secret-backed variables.
     *
     * @param  array<string, string>  $envMapping  Key => GitHub Secret name
     * @param  string  $indent  Indentation prefix
     */
    public function generateWorkflowEnvBlock(array $envMapping, string $indent = ''): string
    {
        if (empty($envMapping)) {
            return '';
        }

        $lines = [];

        foreach ($envMapping as $envKey => $secretName) {
            $lines[] = "{$indent}{$envKey}: \${{ secrets.{$secretName} }}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate workflow sed commands block for the "Update .env values" step.
     *
     * Secret-backed variables use runtime escaping (printf + sed).
     * Static variables use direct values (escaped at generation time).
     *
     * @param  array<string, string>  $envMapping  Key => GitHub Secret name
     * @param  array<string, string>  $envStatic  Key => static value
     * @param  string  $indent  Indentation prefix
     */
    public function generateWorkflowSedBlock(array $envMapping, array $envStatic, string $indent = ''): string
    {
        if (empty($envMapping) && empty($envStatic)) {
            return '';
        }

        $lines = [];

        // Secret-backed variables: escape at runtime
        foreach ($envMapping as $envKey => $secretName) {
            $lines[] = "{$indent}ESCAPED=\$(printf '%s' \"\${{$envKey}}\" | sed 's/[|&\\\\]/\\\\&/g')";
            $lines[] = "{$indent}sed -i \"s|^{$envKey}=.*|{$envKey}=\${ESCAPED}|\" \${ENV_FILE}";
        }

        // Static variables: values known at generation time
        foreach ($envStatic as $envKey => $value) {
            $escapedValue = $this->escapeForSed($value);
            $lines[] = "{$indent}sed -i \"s|^{$envKey}=.*|{$envKey}={$escapedValue}|\" \${ENV_FILE}";
        }

        return implode("\n", $lines);
    }
}
