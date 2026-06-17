<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\SyncAgent;
use SqlSync\LaravelSqlSync\Models\SyncedRecord;
use SqlSync\LaravelSqlSync\Models\SyncLog;

class SyncStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    protected function getStats(): array
    {
        $plugin = SqlSyncFilamentPlugin::get();
        $threshold = (int) config('sqlsync-filament.online_threshold_minutes', 5);
        $cacheTtl = (int) config('sqlsync-filament.stats_cache_seconds', 20);

        if ($plugin->shouldCacheStats()) {
            $stats = Cache::remember(
                $plugin->resolveStatsCacheKey(),
                $cacheTtl,
                fn (): array => $this->calculateStats($plugin, $threshold)
            );
        } else {
            $stats = $this->calculateStats($plugin, $threshold);
        }

        $result = [];

        if ($plugin->isFeatureEnabled('records')) {
            $result[] = Stat::make('Total Records', number_format((int) ($stats['total'] ?? 0)))
                ->description(($stats['active'] ?? 0) . ' active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-circle-stack');

            $result[] = Stat::make('Last Sync', isset($stats['last_sync'])
                ? Carbon::parse($stats['last_sync'])->diffForHumans()
                : 'Never')
                ->description('Most recent data push')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->icon('heroicon-o-clock');
        }

        if ($plugin->isFeatureEnabled('agents')) {
            $result[] = Stat::make('Agents Online', ($stats['agents_online'] ?? 0) . ' / ' . ($stats['agents_total'] ?? 0))
                ->description(($stats['agents_online'] ?? 0) > 0 ? 'Syncing now' : 'No agents online')
                ->descriptionIcon(($stats['agents_online'] ?? 0) > 0 ? 'heroicon-m-signal' : 'heroicon-m-signal-slash')
                ->color(($stats['agents_online'] ?? 0) > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-computer-desktop');
        }

        if ($plugin->isFeatureEnabled('logs')) {
            $result[] = Stat::make('Syncs Today', (string) ($stats['today_logs'] ?? 0))
                ->description('Push operations today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning')
                ->icon('heroicon-o-chart-bar');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateStats(SqlSyncFilamentPlugin $plugin, int $threshold): array
    {
        $stats = [];

        if ($plugin->isFeatureEnabled('records')) {
            $recordsQuery = SyncedRecord::query();

            if ($fn = $plugin->getRecordsQuery()) {
                $recordsQuery = $fn($recordsQuery);
            }

            $stats['total'] = (clone $recordsQuery)->count();
            $stats['active'] = (clone $recordsQuery)->where('is_active', true)->count();
            $stats['last_sync'] = (clone $recordsQuery)->max('synced_at');
        }

        if ($plugin->isFeatureEnabled('agents')) {
            $agentsQuery = SyncAgent::query();

            if ($fn = $plugin->getAgentsQuery()) {
                $agentsQuery = $fn($agentsQuery);
            }

            $stats['agents_online'] = (clone $agentsQuery)->where('last_heartbeat', '>=', now()->subMinutes($threshold))->count();
            $stats['agents_total'] = (clone $agentsQuery)->count();
        }

        if ($plugin->isFeatureEnabled('logs')) {
            $logsQuery = SyncLog::query();

            if ($fn = $plugin->getLogsQuery()) {
                $logsQuery = $fn($logsQuery);
            }

            $stats['today_logs'] = (clone $logsQuery)->whereDate('synced_at', today())->count();
        }

        return $stats;
    }

    protected function getPollingInterval(): ?string
    {
        $interval = config('sqlsync-filament.polling_interval', '30s');

        return $interval ?: null;
    }
}
