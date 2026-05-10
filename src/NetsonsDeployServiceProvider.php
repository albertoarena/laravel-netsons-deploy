<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy;

use AlbertoArena\NetsonsDeploy\Commands\CheckCommand;
use AlbertoArena\NetsonsDeploy\Commands\EnvCommand;
use AlbertoArena\NetsonsDeploy\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;

class NetsonsDeployServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/netsons-deploy.php',
            'netsons-deploy'
        );
    }

    public function boot(): void
    {
        $this->registerPublishing();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CheckCommand::class,
                EnvCommand::class,
            ]);
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/netsons-deploy.php' => config_path('netsons-deploy.php'),
        ], 'netsons-deploy-config');

        $this->publishes([
            __DIR__.'/../stubs/workflows/' => base_path('.github/workflows/'),
        ], 'netsons-deploy-workflows');

        $this->publishes([
            __DIR__.'/../stubs/htaccess/' => base_path('stubs/htaccess/'),
        ], 'netsons-deploy-htaccess');

        $this->publishes([
            __DIR__.'/../stubs/scripts/' => base_path('scripts/'),
        ], 'netsons-deploy-scripts');
    }
}
