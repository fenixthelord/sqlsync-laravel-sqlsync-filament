<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\FieldMappingResource;

class ListFieldMappings extends ListRecords
{
    protected static string $resource = FieldMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Mapping'),
        ];
    }
}
