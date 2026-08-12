<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AccountingCurrencyResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\AccountingCurrencyResource\AccountingCurrencyResource;

class ListAccountingCurrencies extends ListRecords
{
    protected static string $resource = AccountingCurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
