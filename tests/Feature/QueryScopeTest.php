<?php

declare(strict_types=1);

use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;

it('stores records query scope', function (): void {
    $callback = fn ($q) => $q->where('company_id', 1);
    $plugin   = SqlSyncFilamentPlugin::make()->modifyRecordsQueryUsing($callback);
    expect($plugin->getRecordsQuery())->toBe($callback);
});

it('stores agents query scope', function (): void {
    $callback = fn ($q) => $q->where('company_id', 1);
    $plugin   = SqlSyncFilamentPlugin::make()->modifyAgentsQueryUsing($callback);
    expect($plugin->getAgentsQuery())->toBe($callback);
});

it('stores logs query scope', function (): void {
    $callback = fn ($q) => $q->where('company_id', 1);
    $plugin   = SqlSyncFilamentPlugin::make()->modifyLogsQueryUsing($callback);
    expect($plugin->getLogsQuery())->toBe($callback);
});

it('returns null when no query scope is set', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin->getRecordsQuery())->toBeNull();
    expect($plugin->getAgentsQuery())->toBeNull();
    expect($plugin->getLogsQuery())->toBeNull();
});
