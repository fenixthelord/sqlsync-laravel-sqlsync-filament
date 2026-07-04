<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\BridgeLogResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\BridgeLogResource\BridgeLogResource;
use SqlSync\FilamentSqlSync\Filament\Widgets\BridgeStatsWidget;

class ListBridgeLogs extends ListRecords
{
    protected static string $resource = BridgeLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [BridgeStatsWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
