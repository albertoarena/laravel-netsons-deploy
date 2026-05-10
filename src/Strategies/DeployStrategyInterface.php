<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Strategies;

interface DeployStrategyInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string> List of validation errors
     */
    public function validate(array $config): array;

    /**
     * @return array<int, string> List of required GitHub secret names
     */
    public function requiredSecrets(): array;

    /**
     * @return array<int, string> List of required GitHub variable names
     */
    public function requiredVariables(): array;
}
