# SqlSync Filament Plugin

**`sqlsync/laravel-sqlsync-filament`**

Filament v3-V4-V5 Admin Panel plugin for [sqlsync/laravel-sqlsync](https://packagist.org/packages/sqlsync/laravel-sqlsync).

Adds a full admin panel with live stats, agent monitoring, and record browsing — all auto-refreshing every 30 seconds.

---

## Requirements

- PHP 8.2+
- Laravel 10–13
- `sqlsync/laravel-sqlsync` ^1.0
- `filament/filament` ^3.0

---

## Installation

```bash
# 1. Install base package (if not already)
composer require sqlsync/laravel-sqlsync
php artisan sqlsync:install

# 2. Install Filament (if not already)
composer require filament/filament:"^3.0"
php artisan filament:install --panels

# 3. Install this plugin
composer require sqlsync/laravel-sqlsync-filament
php artisan sqlsync-filament:install
```

---

## Setup

Register the plugin in your Filament Panel Provider (`app/Providers/Filament/AdminPanelProvider.php`):

```php
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        // ... your other config ...
        ->plugins([
            SqlSyncFilamentPlugin::make()
                ->withDashboard()
                ->withAgents()
                ->withLogs()
                ->navigationGroup('SqlSync'),
        ]);
}
```

---

## What You Get

### Dashboard
- Total records count (active vs inactive)
- Agents online / total
- Last sync time
- Syncs today

### Records Table
- Search by name, barcode, code
- Filter by preset (Al-Ameen / Al-Bayan) and status
- View full record details including pricing & extra data
- Toggle columns

### Agents Monitor
- Live online/offline status (auto-refreshes every 30s)
- Last heartbeat & last sync time
- Total records synced per agent

### Sync Logs
- Real-time feed of sync operations
- Inserted / Updated / Skipped counts per push
- Status badges

---

## Fluent Configuration

```php
SqlSyncFilamentPlugin::make()
    ->withDashboard(true)          // Show dashboard page
    ->withAgents(true)             // Show agents resource
    ->withLogs(true)               // Show sync logs widget
    ->navigationGroup('My Group')  // Custom sidebar group name
```

---

## Developer

**محمد خلف · +963945235962**
