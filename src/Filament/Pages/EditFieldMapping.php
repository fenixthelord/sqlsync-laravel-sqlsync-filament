<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\FieldMappingResource;

class EditFieldMapping extends EditRecord
{
    protected static string $resource = FieldMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
