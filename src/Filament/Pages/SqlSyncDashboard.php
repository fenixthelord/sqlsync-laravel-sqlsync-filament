<?php

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Pages\Dashboard;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\AgentsOnlineWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\RecentSyncLogsWidget;

class SqlSyncDashboard extends Dashboard
{
    protected static ?string $navigationLabel = 'SqlSync Dashboard';
    protected static ?string $title           = 'SqlSync Overview';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-home';
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

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

    public function getColumns(): int|array
    {
        return 1;
    }
}
