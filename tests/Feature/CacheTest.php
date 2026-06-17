<?php

declare(strict_types=1);

use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;

it('caches stats when no query scopes are set', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin->shouldCacheStats())->toBeTrue();
});

it('disables cache when records query scope is set without custom cache key', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->modifyRecordsQueryUsing(fn ($q) => $q);
    expect($plugin->shouldCacheStats())->toBeFalse();
});

it('disables cache when agents query scope is set without custom cache key', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->modifyAgentsQueryUsing(fn ($q) => $q);
    expect($plugin->shouldCacheStats())->toBeFalse();
});

it('disables cache when logs query scope is set without custom cache key', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->modifyLogsQueryUsing(fn ($q) => $q);
    expect($plugin->shouldCacheStats())->toBeFalse();
});

it('enables cache when custom cache key is provided with query scopes', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->modifyRecordsQueryUsing(fn ($q) => $q)
        ->statsCacheKeyUsing(fn ($user) => 'sqlsync.stats.tenant.1');
    expect($plugin->shouldCacheStats())->toBeTrue();
});

it('resolves custom cache key', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->statsCacheKeyUsing(fn ($user) => 'sqlsync.stats.company.42');
    expect($plugin->resolveStatsCacheKey())->toBe('sqlsync.stats.company.42');
});

it('resolves default cache key when no custom key set', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin->resolveStatsCacheKey())->toBe('sqlsync.dashboard.stats');
});
