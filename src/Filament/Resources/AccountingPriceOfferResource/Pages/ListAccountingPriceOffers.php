<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AccountingPriceOfferResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\AccountingPriceOfferResource\AccountingPriceOfferResource;

class ListAccountingPriceOffers extends ListRecords
{
    protected static string $resource = AccountingPriceOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
