<?php

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use SqlSync\LaravelSqlSync\Models\SyncedRecord;
use SqlSync\LaravelSqlSync\Models\SyncAgent;
use SqlSync\LaravelSqlSync\Models\SyncLog;

class SyncStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalRecords  = SyncedRecord::count();
        $activeRecords = SyncedRecord::where('is_active', true)->count();
        $agentsOnline  = SyncAgent::where('last_heartbeat', '>=', now()->subMinutes(5))->count();
        $agentsTotal   = SyncAgent::count();
        $lastSync      = SyncedRecord::max('synced_at');
        $todayLogs     = SyncLog::whereDate('synced_at', today())->count();

        return [
            Stat::make('Total Records', number_format($totalRecords))
                ->description("{$activeRecords} active")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-circle-stack'),

            Stat::make('Agents Online', "{$agentsOnline} / {$agentsTotal}")
                ->description($agentsOnline > 0 ? 'Syncing now' : 'No agents online')
                ->descriptionIcon($agentsOnline > 0 ? 'heroicon-m-signal' : 'heroicon-m-signal-slash')
                ->color($agentsOnline > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-computer-desktop'),

            Stat::make('Last Sync', $lastSync ? \Carbon\Carbon::parse($lastSync)->diffForHumans() : 'Never')
                ->description('Most recent data push')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->icon('heroicon-o-clock'),

            Stat::make('Syncs Today', $todayLogs)
                ->description('Push operations today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning')
                ->icon('heroicon-o-chart-bar'),
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '30s';
    }
}
