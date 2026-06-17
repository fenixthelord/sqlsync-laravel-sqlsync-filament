<?php

declare(strict_types=1);

it('merges sqlsync-filament config', function (): void {
    expect(config('sqlsync-filament'))->toBeArray();
    expect(config('sqlsync-filament.navigation_group'))->toBe('SqlSync');
});

it('registers install command', function (): void {
    $commands = array_keys(Artisan::all());
    expect($commands)->toContain('sqlsync-filament:install');
});
