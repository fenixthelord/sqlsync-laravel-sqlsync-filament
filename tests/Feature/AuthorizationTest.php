<?php

declare(strict_types=1);

use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;

it('authorizes all users by default', function (): void {
    $plugin = SqlSyncFilamentPlugin::make();

    expect($plugin->isAuthorized())->toBeTrue();
});

it('uses authorization callback', function (): void {
    $plugin = SqlSyncFilamentPlugin::make()
        ->authorizeUsing(fn ($user) => false);

    expect($plugin->isAuthorized())->toBeFalse();
});

it('passes user to authorization callback', function (): void {
    $received = null;

    $plugin = SqlSyncFilamentPlugin::make()
        ->authorizeUsing(function ($user) use (&$received) {
            $received = $user;

            return true;
        });

    $plugin->isAuthorized();

    expect($received)->toBeNull();
});
