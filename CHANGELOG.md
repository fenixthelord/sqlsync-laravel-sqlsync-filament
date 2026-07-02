# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- `AgentResource` — "Test Connection" header action on the Agents list page. Sends a real self-signed HMAC heartbeat request to the configured sync endpoint using the app's own `SQLSYNC_AGENT_SECRET`, so admins can verify the endpoint + secret are wired correctly *before* touching the Windows Agent. Surfaces the exact failure (missing secret, signature mismatch, network error) as a Filament notification instead of a silent 500/401 with no UI feedback.
- `AgentResource` — "تسجيل Agent يدوياً" (Register Agent manually) header action: lets an admin pre-register an `agent_id` / `label` / `company_id` from the panel, for cases where the Windows Agent isn't available yet or troubleshooting is needed. Does not require `spatie/laravel-permission` — no new dependency introduced.

### Fixed
- Previously `AgentResource::canCreate()` returned `false` unconditionally with no alternative path, so any failure in the Agent → Server handshake (missing secret, wrong endpoint, network block) had zero in-panel diagnosis; the only feedback loop was the external Windows Agent's own error message. This release adds an in-panel diagnostic path without weakening the model's real read-only guarantees (`canEdit`/`canDelete` remain `false`; manual registration only inserts, it doesn't let anyone edit synced stats).

## [v1.1.0-beta.1] — 2026-06-17

### Added
- `SqlSyncFilamentPlugin::get()` — safely retrieves Plugin instance from current Panel via `hasPlugin()`
- Authorization: `->authorizeUsing(Closure)` — controls access to all resources, pages, and widgets
- Query scopes: `->modifyRecordsQueryUsing()`, `->modifyAgentsQueryUsing()`, `->modifyLogsQueryUsing()` — multi-tenancy support
- Cache isolation: `->statsCacheKeyUsing()` — tenant-aware cache keys; cache auto-disabled when scopes are active without a custom key
- `->shouldCacheStats()` — no writes to shared cache when query scopes are active
- Feature flags: `->withRecords()` added; all flags now read from config with Fluent API taking priority
- `SyncStatsWidget` stats are now dynamic per feature flag — hidden stats are not computed — calculateStats() only runs queries for enabled features
- Widgets loaded explicitly by Dashboard and ListRecords only — not registered on Panel to avoid appearing on native Dashboard
- `declare(strict_types=1)` on all PHP files
- Full test suite: Plugin, Authorization, Cache, Query Scopes, ServiceProvider
- `phpunit.xml.dist`, `tests/Pest.php`, `tests/TestCase.php`
- `LICENSE` file
- `CHANGELOG.md`

### Fixed
- `composer.json`: added `orchestra/testbench ^11` for Laravel 13 support
- `composer.json`: added `config.allow-plugins` for Pest
- CI: removed all `|| true` — failures now correctly fail the build
- CI: PHP syntax check covers `src` and `config` directories

### Changed
- Widgets no longer registered via `Panel::widgets()` — prevents them from appearing on the native Dashboard
- `SyncStatsWidget` no longer writes to cache when tenant query scopes are active

## [v1.0.x] — Initial releases

See git history for early development changes.
