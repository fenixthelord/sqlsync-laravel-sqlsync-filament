<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use SqlSync\FilamentSqlSync\Filament\Pages\SqlSyncDashboard;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\AgentResource;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\RecordResource;

class SqlSyncFilamentPlugin implements Plugin
{
    // ── Feature flags (null = read from config) ──────────────────────────────
    protected ?bool $showDashboard = null;
    protected ?bool $showRecords   = null;
    protected ?bool $showAgents    = null;
    protected ?bool $showLogs      = null;

    // ── Navigation ────────────────────────────────────────────────────────────
    protected ?string $navigationGroup = null;

    // ── Authorization ─────────────────────────────────────────────────────────
    protected ?Closure $authCallback = null;

    // ── Query Scopes ──────────────────────────────────────────────────────────
    protected ?Closure $recordsQuery = null;
    protected ?Closure $agentsQuery  = null;
    protected ?Closure $logsQuery    = null;

    // ── Cache ─────────────────────────────────────────────────────────────────
    protected ?Closure $statsCacheKeyCallback = null;

    // ── Factory ───────────────────────────────────────────────────────────────
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Retrieve the Plugin instance registered in the current Filament Panel.
     * Falls back to a fresh instance (e.g. during artisan / tests).
     */
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

    // ── Fluent API ─────────────────────────────────────────────────────────────

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

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    /**
     * Authorization callback — receives the authenticated user.
     * Return true to allow access.
     *
     * Example:
     *   ->authorizeUsing(fn ($user) => $user->hasRole('admin'))
     */
    public function authorizeUsing(Closure $callback): static
    {
        $this->authCallback = $callback;
        return $this;
    }

    /**
     * Scope the Records query — useful for multi-tenancy.
     */
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

    /**
     * Custom cache key for stats — REQUIRED when using query scopes with multi-tenancy
     * to prevent data leaking between tenants.
     *
     * Example:
     *   ->statsCacheKeyUsing(fn ($user) => "sqlsync.stats.{$user->company_id}")
     */
    public function statsCacheKeyUsing(Closure $callback): static
    {
        $this->statsCacheKeyCallback = $callback;
        return $this;
    }

    // ── Getters ────────────────────────────────────────────────────────────────

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

    /**
     * Whether stats should be cached.
     * Returns false when any query scope is active without a custom cache key,
     * to prevent data leaking between tenants.
     */
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
            'records'   => $this->showRecords   ?? (bool) config('sqlsync-filament.features.records',   true),
            'agents'    => $this->showAgents    ?? (bool) config('sqlsync-filament.features.agents',    true),
            'logs'      => $this->showLogs      ?? (bool) config('sqlsync-filament.features.logs',      true),
            default     => false,
        };
    }

    // ── Register with Filament Panel ───────────────────────────────────────────

    public function register(Panel $panel): void
    {
        $resources = [];
        $pages     = [];

        if ($this->isFeatureEnabled('records')) {
            $resources[] = RecordResource::class;
        }

        if ($this->isFeatureEnabled('agents')) {
            $resources[] = AgentResource::class;
        }

        if ($this->isFeatureEnabled('dashboard')) {
            $pages[] = SqlSyncDashboard::class;
        }

        // Widgets are NOT registered on the panel to avoid showing them
        // on the native Filament Dashboard. They are loaded explicitly by
        // SqlSyncDashboard::getWidgets() and RecordResource ListRecords header.
        $panel
            ->resources($resources)
            ->pages($pages);
    }

    public function boot(Panel $panel): void {}
}
