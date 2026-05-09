<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Services;

class HtaccessGenerator
{
    public function generateRoot(): string
    {
        return <<<'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirect all requests to public/ subdirectory
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
</IfModule>
HTACCESS;
    }

    public function generatePublic(): string
    {
        return <<<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-TOKEN Header
    RewriteCond %{HTTP:X-XSRF-TOKEN} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-TOKEN}]

    # Redirect trailing slashes if not a folder
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send requests to front controller (index.php)
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS;
    }
}
