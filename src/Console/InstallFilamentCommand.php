<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Console;

use Composer\InstalledVersions;
use Filament\Panel;
use Illuminate\Console\Command;
use SqlSync\LaravelSqlSync\SqlSyncServiceProvider;

class InstallFilamentCommand extends Command
{
    protected $signature = 'sqlsync-filament:install';

    protected $description = 'Install SqlSync Filament Plugin';

    public function handle(): void
    {
        $this->info('Installing SqlSync Filament Plugin...');

        if (! class_exists(Panel::class)) {
            $this->error('Filament is not installed.');
            $this->line('Run: <comment>composer require filament/filament</comment>');
            $this->line('Then: <comment>php artisan filament:install --panels</comment>');

            return;
        }

        $version = InstalledVersions::getVersion('filament/filament') ?? '0';
        $major = (int) $version;

        if ($major < 4) {
            $this->error('Filament v'.$major.' is not supported. This plugin requires Filament v4 or v5.');
            $this->line('Upgrade: <comment>composer require filament/filament -W</comment>');

            return;
        }

        $this->line('  <fg=green>✓</> Filament v'.$version.' detected');

        if (! class_exists(SqlSyncServiceProvider::class)) {
            $this->error('sqlsync/laravel-sqlsync is not installed.');
            $this->line('Run: <comment>composer require sqlsync/laravel-sqlsync</comment>');
            $this->line('Then: <comment>php artisan sqlsync:install</comment>');

            return;
        }

        $this->line('  <fg=green>✓</> sqlsync/laravel-sqlsync detected');

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
        $this->line("          ->navigationGroup('SqlSync'),");
        $this->line('  ])');
        $this->newLine();
        $this->line('Optional — Authorization:');
        $this->line("      ->authorizeUsing(fn (\$user) => \$user->hasRole('admin'))");
        $this->newLine();
        $this->line('Optional — Multi-tenancy:');
        $this->line("      ->modifyRecordsQueryUsing(fn (\$q) => \$q->where('company_id', auth()->user()->company_id))");
        $this->line("      ->statsCacheKeyUsing(fn (\$user) => 'sqlsync.stats.'.\$user->company_id)");
    }
}
