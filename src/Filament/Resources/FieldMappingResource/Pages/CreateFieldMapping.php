<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\FieldMappingResource;

class CreateFieldMapping extends CreateRecord
{
    protected static string $resource = FieldMappingResource::class;
}
