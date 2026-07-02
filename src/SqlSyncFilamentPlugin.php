<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use SqlSync\FilamentSqlSync\Filament\Pages\SqlSyncDashboard;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\AgentResource;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\FieldMappingResource;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\RecordResource;

class SqlSyncFilamentPlugin implements Plugin
{
    protected ?bool $showDashboard = null;

    protected ?bool $showRecords = null;

    protected ?bool $showAgents = null;

    protected ?bool $showLogs = null;

    protected ?bool $showMappings = null;

    protected ?string $navigationGroup = null;

    protected ?Closure $authCallback = null;

    protected ?Closure $recordsQuery = null;

    protected ?Closure $agentsQuery = null;

    protected ?Closure $logsQuery = null;

    protected ?Closure $mappingsQuery = null;

    protected ?Closure $statsCacheKeyCallback = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        $panel = Filament::getCurrentPanel();

        if ($panel !== null && $panel->hasPlugin('sqlsync')) {
            /** @var static $plugin */
            $plugin = $panel->getPlugin('sqlsync');

            return $plugin;
        }

        return app(static::class);
    }

    public function getId(): string
    {
        return 'sqlsync';
    }

    public function withDashboard(bool $show = true): static
    {
        $this->showDashboard = $show;

        return $this;
    }

    public function withRecords(bool $show = true): static
    {
        $this->showRecords = $show;

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

    public function withMappings(bool $show = true): static
    {
        $this->showMappings = $show;

        return $this;
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function authorizeUsing(Closure $callback): static
    {
        $this->authCallback = $callback;

        return $this;
    }

    public function modifyRecordsQueryUsing(Closure $callback): static
    {
        $this->recordsQuery = $callback;

        return $this;
    }

    public function modifyAgentsQueryUsing(Closure $callback): static
    {
        $this->agentsQuery = $callback;

        return $this;
    }

    public function modifyLogsQueryUsing(Closure $callback): static
    {
        $this->logsQuery = $callback;

        return $this;
    }

    public function modifyMappingsQueryUsing(Closure $callback): static
    {
        $this->mappingsQuery = $callback;

        return $this;
    }

    public function statsCacheKeyUsing(Closure $callback): static
    {
        $this->statsCacheKeyCallback = $callback;

        return $this;
    }

    public function getNavigationGroup(): string
    {
        return $this->navigationGroup
            ?? config('sqlsync-filament.navigation_group', 'SqlSync');
    }

    public function isAuthorized(): bool
    {
        if ($this->authCallback === null) {
            return true;
        }

        return (bool) ($this->authCallback)(auth()->user());
    }

    public function getRecordsQuery(): ?Closure
    {
        return $this->recordsQuery;
    }

    public function getAgentsQuery(): ?Closure
    {
        return $this->agentsQuery;
    }

    public function getLogsQuery(): ?Closure
    {
        return $this->logsQuery;
    }

    public function getMappingsQuery(): ?Closure
    {
        return $this->mappingsQuery;
    }

    public function shouldCacheStats(): bool
    {
        if ($this->statsCacheKeyCallback !== null) {
            return true;
        }

        return $this->recordsQuery === null
            && $this->agentsQuery === null
            && $this->logsQuery === null;
    }

    public function resolveStatsCacheKey(): string
    {
        if ($this->statsCacheKeyCallback !== null) {
            return (string) ($this->statsCacheKeyCallback)(auth()->user());
        }

        return 'sqlsync.dashboard.stats';
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return match ($feature) {
            'dashboard' => $this->showDashboard ?? (bool) config('sqlsync-filament.features.dashboard', true),
            'records' => $this->showRecords ?? (bool) config('sqlsync-filament.features.records', true),
            'agents' => $this->showAgents ?? (bool) config('sqlsync-filament.features.agents', true),
            'logs' => $this->showLogs ?? (bool) config('sqlsync-filament.features.logs', true),
            'mappings' => $this->showMappings ?? (bool) config('sqlsync-filament.features.mappings', true),
            default => false,
        };
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $pages = [];

        if ($this->isFeatureEnabled('records')) {
            $resources[] = RecordResource::class;
        }

        if ($this->isFeatureEnabled('agents')) {
            $resources[] = AgentResource::class;
        }

        if ($this->isFeatureEnabled('mappings')) {
            $resources[] = FieldMappingResource::class;
        }

        if ($this->isFeatureEnabled('dashboard')) {
            $pages[] = SqlSyncDashboard::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages);
    }

    public function boot(Panel $panel): void {}
}
