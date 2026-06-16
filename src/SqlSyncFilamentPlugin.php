<?php

namespace SqlSync\FilamentSqlSync;

use Filament\Contracts\Plugin;
use Filament\Panel;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\RecordResource;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\AgentResource;
use SqlSync\FilamentSqlSync\Filament\Pages\SqlSyncDashboard;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\AgentsOnlineWidget;
use SqlSync\FilamentSqlSync\Filament\Widgets\RecentSyncLogsWidget;

class SqlSyncFilamentPlugin implements Plugin
{
    protected bool $showDashboard = true;
    protected bool $showAgents    = true;
    protected bool $showLogs      = true;
    protected string $navigationGroup = 'SqlSync';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'sqlsync';
    }

    // ── Fluent Configuration ────────────────────────────────────────────────

    public function withDashboard(bool $show = true): static
    {
        $this->showDashboard = $show;
        return $this;
    }

    public function withAgents(bool $show = true): static
    {
        $this->showAgents = $show;
        return $this;
    }

    public function withLogs(bool $show = true): static
    {
        $this->showLogs = $show;
        return $this;
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    // ── Register with Filament Panel ────────────────────────────────────────

    public function register(Panel $panel): void
    {
        $resources = [RecordResource::class];
        $pages     = [];
        $widgets   = [SyncStatsWidget::class];

        if ($this->showAgents) {
            $resources[] = AgentResource::class;
            $widgets[]   = AgentsOnlineWidget::class;
        }

        if ($this->showDashboard) {
            $pages[] = SqlSyncDashboard::class;
        }

        if ($this->showLogs) {
            $widgets[] = RecentSyncLogsWidget::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets);
    }

    public function boot(Panel $panel): void {}

    public function getNavigationGroup(): string
    {
        return $this->navigationGroup;
    }
}
