<?php

namespace Brainy\ErrorNotifier\Tests;

use Brainy\ErrorNotifier\ErrorNotifierServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ErrorNotifierServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.env', 'production');
        $app['config']->set('cache.default', 'array');
    }
}
