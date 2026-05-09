<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Services;

class ProxyIndexGenerator
{
    public function generate(string $releasePath): string
    {
        $releasePath = rtrim($releasePath, '/');

        return <<<PHP
<?php

/**
 * Proxy index.php — routes requests to the active Laravel release.
 *
 * This file sits in the document root (public_html/) and bootstraps
 * Laravel from the current release directory.
 */

// Check for maintenance mode
if (file_exists('{$releasePath}/storage/framework/maintenance.php')) {
    require '{$releasePath}/storage/framework/maintenance.php';
}

// Bootstrap Laravel from the active release
require '{$releasePath}/public/index.php';
PHP;
    }
}
