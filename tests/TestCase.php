<?php

declare(strict_types=1);

namespace Tests;

use Laravel\McpProviders\McpProvidersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpProvidersServiceProvider::class,
        ];
    }
}
