<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AccountingProductCurrencyBindingResource;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SqlSync\FilamentSqlSync\Filament\Resources\AccountingProductCurrencyBindingResource\Pages\ListAccountingProductCurrencyBindings;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\AccountingProductCurrencyBinding;

class AccountingProductCurrencyBindingResource extends Resource
{
    protected static ?string $model = AccountingProductCurrencyBinding::class;

    protected static ?string $navigationLabel = 'Currency Bindings';

    protected static ?string $modelLabel = 'Currency Binding';

    protected static ?string $pluralModelLabel = 'Currency Bindings';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-link';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if ($fn = SqlSyncFilamentPlugin::get()->getAccountingQuery()) {
            $query = $fn($query);
        }

        return $query;
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
                TextColumn::make('currency_source_id')
                    ->label('Currency Source ID')
                    ->searchable()
                    ->copyable(),
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
            'index' => ListAccountingProductCurrencyBindings::route('/'),
        ];
    }
}
