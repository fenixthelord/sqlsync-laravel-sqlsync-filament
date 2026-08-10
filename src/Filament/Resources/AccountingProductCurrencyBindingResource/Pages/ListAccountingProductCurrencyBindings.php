<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AccountingProductCurrencyBindingResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\AccountingProductCurrencyBindingResource\AccountingProductCurrencyBindingResource;

class ListAccountingProductCurrencyBindings extends ListRecords
{
    protected static string $resource = AccountingProductCurrencyBindingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
