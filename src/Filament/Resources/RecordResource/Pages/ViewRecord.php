<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\Pages;

use Filament\Resources\Pages\ViewRecord as BaseViewRecord;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\RecordResource;

class ViewRecord extends BaseViewRecord
{
    protected static string $resource = RecordResource::class;
}
