<?php

declare(strict_types=1);

use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;

it('creates a plugin instance via make()', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin)->toBeInstanceOf(SqlSyncFilamentPlugin::class);
});

it('has the correct plugin id', function (): void {
    expect(SqlSyncFilamentPlugin::make()->getId())->toBe('sqlsync');
});

it('enables all features by default', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin->isFeatureEnabled('dashboard'))->toBeTrue();
    expect($plugin->isFeatureEnabled('records'))->toBeTrue();
    expect($plugin->isFeatureEnabled('agents'))->toBeTrue();
    expect($plugin->isFeatureEnabled('logs'))->toBeTrue();
});

it('disables features via fluent api', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->withDashboard(false)
        ->withRecords(false)
        ->withAgents(false)
        ->withLogs(false);

    expect($plugin->isFeatureEnabled('dashboard'))->toBeFalse();
    expect($plugin->isFeatureEnabled('records'))->toBeFalse();
    expect($plugin->isFeatureEnabled('agents'))->toBeFalse();
    expect($plugin->isFeatureEnabled('logs'))->toBeFalse();
});

it('returns custom navigation group', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()->navigationGroup('My Group');
    expect($plugin->getNavigationGroup())->toBe('My Group');
});

it('returns default navigation group from config', function (): void {
    config()->set('sqlsync-filament.navigation_group', 'SqlSync');
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin->getNavigationGroup())->toBe('SqlSync');
});

it('unknown feature returns false', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();
    expect($plugin->isFeatureEnabled('nonexistent'))->toBeFalse();
});

it('fluent api overrides config feature flags', function (): void {
    config()->set('sqlsync-filament.features.agents', true);

    $plugin = SqlSyncFilamentPlugin::make()->withAgents(false);
    expect($plugin->isFeatureEnabled('agents'))->toBeFalse();
});

it('returns default navigation group when none set', function (): void {
    config()->set('sqlsync-filament.navigation_group', 'SqlSync');
    expect(SqlSyncFilamentPlugin::make()->getNavigationGroup())->toBe('SqlSync');
});
