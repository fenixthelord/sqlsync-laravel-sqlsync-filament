<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SqlSync\LaravelSqlSync\SqlSyncServiceProvider;
use SqlSync\FilamentSqlSync\SqlSyncFilamentServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SqlSyncServiceProvider::class,
            SqlSyncFilamentServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
