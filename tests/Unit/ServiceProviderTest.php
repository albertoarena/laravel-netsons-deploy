<?php

declare(strict_types=1);

use AlbertoArena\NetsonsDeploy\NetsonsDeployServiceProvider;
use AlbertoArena\NetsonsDeploy\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

uses(TestCase::class);

describe('publish tags', function () {
    // Guard: proves the provider actually booted and registered its publishes.
    // Without this, the "removed tag" assertions below would pass vacuously.
    it('still publishes the config file', function () {
        $paths = ServiceProvider::pathsToPublish(NetsonsDeployServiceProvider::class, 'netsons-deploy-config');

        expect($paths)->not->toBeEmpty();
        expect(implode('', array_keys($paths)))->toContain('config/netsons-deploy.php');
    });

    it('no longer registers the scripts publish tag', function () {
        expect(ServiceProvider::pathsToPublish(NetsonsDeployServiceProvider::class, 'netsons-deploy-scripts'))
            ->toBeEmpty();
    });

    it('no longer registers the workflows publish tag', function () {
        expect(ServiceProvider::pathsToPublish(NetsonsDeployServiceProvider::class, 'netsons-deploy-workflows'))
            ->toBeEmpty();
    });

    it('no longer registers the htaccess publish tag', function () {
        expect(ServiceProvider::pathsToPublish(NetsonsDeployServiceProvider::class, 'netsons-deploy-htaccess'))
            ->toBeEmpty();
    });
});

describe('removed stub directories', function () {
    it('ships no stubs/scripts directory', function () {
        expect(is_dir(__DIR__.'/../../stubs/scripts'))->toBeFalse();
    });

    it('ships no stubs/htaccess directory', function () {
        expect(is_dir(__DIR__.'/../../stubs/htaccess'))->toBeFalse();
    });

    it('still ships the workflow stubs used by netsons:install', function () {
        expect(file_exists(__DIR__.'/../../stubs/workflows/deploy.yml.stub'))->toBeTrue();
    });
});
