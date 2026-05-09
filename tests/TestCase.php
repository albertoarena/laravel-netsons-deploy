<?php

declare(strict_types=1);

namespace AlbertoArena\NetsonsDeploy\Tests;

use AlbertoArena\NetsonsDeploy\NetsonsDeployServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            NetsonsDeployServiceProvider::class,
        ];
    }
}
