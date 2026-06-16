<?php

namespace SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\RecordResource;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;

class ListRecords extends ListRecords
{
    protected static string $resource = RecordResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SyncStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
