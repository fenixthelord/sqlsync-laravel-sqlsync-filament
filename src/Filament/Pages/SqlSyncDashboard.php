<?php

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Pages\Dashboard;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\AgentsOnlineWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\RecentSyncLogsWidget;

class SqlSyncDashboard extends Dashboard
{
    // No type hints — compatible with Filament v3/v4/v5
    protected static $navigationIcon  = 'heroicon-o-home';
    protected static $navigationSort  = 0;

    protected static ?string $navigationLabel = 'SqlSync Dashboard';
    protected static ?string $title           = 'SqlSync Overview';

    public static function getNavigationGroup(): ?string
    {
        return app(\SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin::class)->getNavigationGroup();
    }

    public function getWidgets(): array
    {
        return [
            SyncStatsWidget::class,
            AgentsOnlineWidget::class,
            RecentSyncLogsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
