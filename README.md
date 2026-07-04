# SqlSync Filament Plugin

**`sqlsync/laravel-sqlsync-filament`**

Filament v4/v5 Admin Panel plugin for [sqlsync/laravel-sqlsync](https://packagist.org/packages/sqlsync/laravel-sqlsync).

---

## Compatibility

| PHP  | Laravel | Filament |
|------|---------|----------|
| 8.2+ | 11      | 4.x      |
| 8.3+ | 12      | 4.x / 5.x |
| 8.4+ | 13      | 5.x      |

---

## Installation

```bash
# 1. Install base package
composer require sqlsync/laravel-sqlsync
php artisan sqlsync:install

# 2. Install Filament v4 or v5
composer require filament/filament
php artisan filament:install --panels

# 3. Install this plugin
composer require sqlsync/laravel-sqlsync-filament
php artisan sqlsync-filament:install
```

---

## Setup

Register the plugin in your Filament Panel Provider:

```php
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            SqlSyncFilamentPlugin::make()
                ->navigationGroup('SqlSync'),
        ]);
}
```

---

## Feature Flags

Fluent API takes priority over config:

```php
SqlSyncFilamentPlugin::make()
    ->withDashboard(false)  // hide SqlSync dashboard
    ->withRecords(false)    // hide records resource
    ->withAgents(false)     // hide agents resource + widget
    ->withLogs(false)       // hide logs widget
    ->withMappings(true)    // legacy field-label resource — off by default, superseded by Product Bridge
    ->withBridge(false)     // hide Product Bridge + Bridge Activity
```

Or via `config/sqlsync-filament.php`:

```php
'features' => [
    'dashboard' => true,
    'records'   => true,
    'agents'    => true,
    'logs'      => true,
    'mappings'  => false,
    'bridge'    => true,
],
```

---

## Authorization

```php
SqlSyncFilamentPlugin::make()
    ->authorizeUsing(fn ($user) => $user->hasRole('admin'))
```

Works with any permission system. No direct Spatie dependency.

---

## Multi-Tenancy

```php
SqlSyncFilamentPlugin::make()
    ->modifyRecordsQueryUsing(
        fn ($query) => $query->where('company_id', auth()->user()->company_id)
    )
    ->modifyAgentsQueryUsing(
        fn ($query) => $query->where('company_id', auth()->user()->company_id)
    )
    ->modifyLogsQueryUsing(
        fn ($query) => $query->where('company_id', auth()->user()->company_id)
    )
    // Required when using query scopes — prevents cache data leaks between tenants
    ->statsCacheKeyUsing(
        fn ($user) => "sqlsync.stats.{$user->company_id}"
    )
```

> **Important:** If you use query scopes without `statsCacheKeyUsing`, the stats cache is automatically disabled to prevent data leaking between tenants.

---

## Configuration

```bash
php artisan vendor:publish --tag=sqlsync-filament-config
```

```php
// config/sqlsync-filament.php
return [
    'navigation_group'         => 'SqlSync',
    'navigation_icon'          => 'heroicon-o-arrow-path-rounded-square',

    'features' => [
        'dashboard' => true,
        'records'   => true,
        'agents'    => true,
        'logs'      => true,
    ],

    'polling_interval'         => '30s',  // null to disable
    'online_threshold_minutes' => 5,
    'recent_logs_limit'        => 20,
    'stats_cache_seconds'      => 20,
];
```

---

## What You Get

| Section | Description |
|---------|-------------|
| SqlSync Dashboard | Stats: total records, agents online, last sync, syncs today |
| Records | Searchable + filterable table with detail view, "إنشاء منتج" manual bridge action per row |
| Agents | Live monitor with Online/Offline status + "Test Connection" self-check |
| Sync Logs | Recent sync operations feed |
| Product Bridge | Visual field mapping from synced records to *your* product model — no code, no fixed schema assumption |
| Bridge Activity | Full history of every created / updated / skipped decision, with stats and filters |

Dashboard, Agents, and Logs widgets auto-refresh based on `polling_interval`. The Records table does not poll.

---

## Product Bridge & background re-apply

See the core package's [`sqlsync/laravel-sqlsync` README](https://github.com/fenixthelord/sqlsync-package#product-bridge) for the full Product Bridge reference (field mapping, auto-category, `sqlsync:reapply-bridge` CLI command). From this panel, the **Product Bridge** page lets any user (not just a developer) configure it visually and trigger a background re-apply with a live progress bar — no terminal required.

---

## Developer

**Muhammad Khalaf** — fenixthelord@gmail.com
