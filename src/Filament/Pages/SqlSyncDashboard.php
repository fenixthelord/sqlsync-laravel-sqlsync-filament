<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Pages\Dashboard;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\FilamentSqlSync\Filament\Widgets\AgentsOnlineWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\RecentSyncLogsWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;

class SqlSyncDashboard extends Dashboard
{
    // Stable slug — does not conflict with native Filament dashboard "/"
    protected static ?string $slug = 'sql-sync-dashboard';

    protected static string $routePath = '/sqlsync';

    protected static ?string $navigationLabel = 'SqlSync Dashboard';

    protected static ?string $title = 'SqlSync Overview';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return config('sqlsync-filament.navigation_icon', 'heroicon-o-arrow-path-rounded-square');
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

    public static function getNavigationGroup(): ?string
    {
        return SqlSyncFilamentPlugin::get()->getNavigationGroup();
    }

    public static function canAccess(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    // Plugin is the single source of truth — widgets are loaded here only,
    // not via Panel::widgets(), so they won't appear on the native Dashboard.
    public function getWidgets(): array
    {
        $plugin  = SqlSyncFilamentPlugin::get();
        $widgets = [];

        // Only show SyncStatsWidget when at least one feature has stats to display
        $hasStats = $plugin->isFeatureEnabled('records')
            || $plugin->isFeatureEnabled('agents')
            || $plugin->isFeatureEnabled('logs');

        if ($hasStats) {
            $widgets[] = SyncStatsWidget::class;
        }

        if ($plugin->isFeatureEnabled('agents')) {
            $widgets[] = AgentsOnlineWidget::class;
        }

        if ($plugin->isFeatureEnabled('logs')) {
            $widgets[] = RecentSyncLogsWidget::class;
        }

        return $widgets;
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
