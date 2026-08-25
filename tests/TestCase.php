<?php

declare(strict_types=1);

namespace Flick\Laravel\Tests;

use Flick\Laravel\FlickServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FlickServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up any environment configuration
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
