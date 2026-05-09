<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Services;

class ReleaseManager
{
    public function generateTimestamp(): string
    {
        return gmdate('YmdHis');
    }

    public function getCleanupCommand(string $deployPath, int $keep): string
    {
        return <<<BASH
cd ~/{$deployPath}/releases && ls -1d */ 2>/dev/null | sort -r | tail -n +{$keep} | xargs -I {} rm -rf {}
BASH;
    }

    public function getCreateCommand(string $deployPath, string $timestamp): string
    {
        $releasePath = "{$deployPath}/releases/{$timestamp}";

        return <<<BASH
mkdir -p ~/{$releasePath}
if [ -L ~/{$deployPath}/current ]; then
    cp -a ~/{$deployPath}/current/. ~/{$releasePath}/
fi
BASH;
    }

    public function getSwitchCommand(string $deployPath, string $timestamp, string $phpBinary): string
    {
        $releasePath = "{$deployPath}/releases/{$timestamp}";

        return <<<BASH
cd ~/{$releasePath}
{$phpBinary} artisan cache:clear
{$phpBinary} artisan config:cache
{$phpBinary} artisan route:cache
{$phpBinary} artisan view:cache
{$phpBinary} artisan event:cache
ln -sfn ~/{$releasePath} ~/{$deployPath}/current
BASH;
    }
}
