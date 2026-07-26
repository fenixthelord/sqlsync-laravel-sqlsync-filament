# ⚠️ DEPRECATED — Do Not Install

> **`sqlsync/laravel-sqlsync-filament` is no longer maintained as a standalone package.**

## What happened?

All Filament resources, pages, and dashboards this plugin provided have been **rebuilt directly inside** [`fenixthelord/sqlsync-store`](https://github.com/fenixthelord/sqlsync-store), where they are auto-discovered by the panel provider with zero configuration.

## What replaced it?

| Legacy Filament plugin | Current (in `sqlsync-store`) |
|---|---|
| Manual: add plugin to `StorePanelProvider` | Auto: `->discoverResources()` + `->discoverPages()` finds everything |
| `SqlSyncFilamentPlugin::make()->navigationGroup('SqlSync')` | Nothing to configure |
| `->authorizeUsing(...)` closure | Integrated via `HasFeatureGating` trait per-resource |
| `->modifyRecordsQueryUsing(...)` for multi-tenant | Automatic via `stancl/tenancy` bootstrappers |

The rebuilt UI covers:

- `AgentDeviceResource` — list of paired devices with online/offline status
- `PairAgentPage` — QR code + display code for pairing a new Agent
- Feature-gated by `agent_sync` — hidden for tenants without the addon

## What should I do?

- **New projects**: Use [`sqlsync-store`](https://github.com/fenixthelord/sqlsync-store) directly. Nothing to `composer require`.
- **Old projects that installed this plugin**: The functionality is duplicated inside `sqlsync-store`. Remove the composer requirement and remove the plugin registration from your `PanelProvider`.

## Why keep this repo public?

For historical reference and `composer.lock` compatibility. It will not receive updates.

Original documentation is preserved at [`README-legacy.md`](./README-legacy.md).

---

_Superseded by `sqlsync-store`._
