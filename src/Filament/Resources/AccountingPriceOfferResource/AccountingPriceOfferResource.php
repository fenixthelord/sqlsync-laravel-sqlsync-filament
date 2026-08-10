<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AccountingPriceOfferResource;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use SqlSync\FilamentSqlSync\Filament\Resources\AccountingPriceOfferResource\Pages\ListAccountingPriceOffers;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\AccountingPriceOffer;

class AccountingPriceOfferResource extends Resource
{
    protected static ?string $model = AccountingPriceOffer::class;

    protected static ?string $navigationLabel = 'Accounting Price Offers';

    protected static ?string $modelLabel = 'Accounting Price Offer';

    protected static ?string $pluralModelLabel = 'Accounting Price Offers';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationSort(): ?int
    {
        return 8;
    }

    public static function getNavigationGroup(): ?string
    {
        return SqlSyncFilamentPlugin::get()->getNavigationGroup();
    }

    public static function canViewAny(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized()
            && SqlSyncFilamentPlugin::get()->isFeatureEnabled('accounting');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('synced_at', 'desc')
            ->columns([
                TextColumn::make('source_provider')
                    ->label('Provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('product_source_id')
                    ->label('Product Source ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('price_key')
                    ->label('Price Key')
                    ->badge()
                    ->searchable(),
                TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->sortable(),
                TextColumn::make('currency_source_id')
                    ->label('Currency Source ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->placeholder('—'),
                TextColumn::make('synced_at')
                    ->label('Synced')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_provider')
                    ->label('Provider')
                    ->options([
                        'al_ameen' => 'Al-Ameen',
                        'al_bayan' => 'Al-Bayan',
                    ]),
            ])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingPriceOffers::route('/'),
        ];
    }
}
