<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync;

use Illuminate\Support\ServiceProvider;
use SqlSync\FilamentSqlSync\Console\InstallFilamentCommand;

class SqlSyncFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sqlsync-filament.php', 'sqlsync-filament');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sqlsync-filament.php' => config_path('sqlsync-filament.php'),
            ], 'sqlsync-filament-config');

            $this->commands([
                InstallFilamentCommand::class,
            ]);
        }
    }
}
