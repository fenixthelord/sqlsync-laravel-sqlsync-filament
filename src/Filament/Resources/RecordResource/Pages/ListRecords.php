<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\Pages;

use Filament\Resources\Pages\ListRecords as BaseListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\RecordResource;
use SqlSync\FilamentSqlSync\Filament\Widgets\SyncStatsWidget;

class ListRecords extends BaseListRecords
{
    protected static string $resource = RecordResource::class;

    protected function getHeaderWidgets(): array
    {
        return [SyncStatsWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
