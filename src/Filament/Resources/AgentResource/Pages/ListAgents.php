<?php

namespace SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\AgentResource;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
