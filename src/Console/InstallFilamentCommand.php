<?php

namespace SqlSync\FilamentSqlSync\Console;

use Illuminate\Console\Command;

class InstallFilamentCommand extends Command
{
    protected $signature   = 'sqlsync-filament:install';
    protected $description = 'Install SqlSync Filament Plugin';

    public function handle(): void
    {
        $this->info('Installing SqlSync Filament Plugin...');

        // Check Filament is installed
        if (! class_exists(\Filament\Panel::class)) {
            $this->error('Filament v3 is not installed.');
            $this->line('Run: <comment>composer require filament/filament:"^3.0"</comment>');
            $this->line('Then: <comment>php artisan filament:install --panels</comment>');
            return;
        }

        // Check base package is installed
        if (! class_exists(\SqlSync\LaravelSqlSync\SqlSyncServiceProvider::class)) {
            $this->error('sqlsync/laravel-sqlsync is not installed.');
            $this->line('Run: <comment>composer require sqlsync/laravel-sqlsync</comment>');
            $this->line('Then: <comment>php artisan sqlsync:install</comment>');
            return;
        }

        // Publish config
        $this->callSilently('vendor:publish', ['--tag' => 'sqlsync-filament-config']);
        $this->line('  <fg=green>✓</> Config published → config/sqlsync-filament.php');

        $this->newLine();
        $this->info('SqlSync Filament Plugin installed!');
        $this->newLine();
        $this->line('Register the plugin in your Filament Panel Provider:');
        $this->newLine();
        $this->line('  <comment>use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;</comment>');
        $this->newLine();
        $this->line('  ->plugins([');
        $this->line('      SqlSyncFilamentPlugin::make()');
        $this->line('          ->withDashboard()');
        $this->line('          ->withAgents()');
        $this->line('          ->withLogs()');
        $this->line('          ->navigationGroup(\'SqlSync\'),');
        $this->line('  ])');
    }
}
