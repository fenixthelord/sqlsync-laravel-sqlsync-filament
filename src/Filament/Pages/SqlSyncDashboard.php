<?php

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Pages\Dashboard;
use SqlSync\FilamentSqlSync\Filament\Widgets\AgentsOnlineWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\RecentSyncLogsWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;

class SqlSyncDashboard extends Dashboard
{
    /**
     * Keep a stable route name:
     * filament.{panel}.pages.sql-sync-dashboard
     */
    protected static ?string $slug = 'sql-sync-dashboard';

    /**
     * Do not use "/" because the native Filament dashboard already uses it.
     */
    protected static string $routePath = '/sqlsync';

    protected static ?string $navigationLabel = 'SqlSync Dashboard';

    protected static ?string $title = 'SqlSync Overview';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-path-rounded-square';
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

    public static function getNavigationGroup(): ?string
    {
        return app(
            \SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin::class
        )->getNavigationGroup();
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