<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AccountingCurrencyResource;

use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SqlSync\FilamentSqlSync\Filament\Resources\AccountingCurrencyResource\Pages\ListAccountingCurrencies;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\AccountingCurrency;

class AccountingCurrencyResource extends Resource
{
    protected static ?string $model = AccountingCurrency::class;

    protected static ?string $navigationLabel = 'Accounting Currencies';

    protected static ?string $modelLabel = 'Accounting Currency';

    protected static ?string $pluralModelLabel = 'Accounting Currencies';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
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
            ->defaultSort('is_base', 'desc')
            ->columns([
                TextColumn::make('source_provider')
                    ->label('Provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('provider_source_id')
                    ->label('Source ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('iso_code')
                    ->label('ISO')
                    ->placeholder('—')
                    ->searchable(),
                BadgeColumn::make('is_base')
                    ->label('Base')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Base' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('rate_to_base')
                    ->label('Rate to Base')
                    ->placeholder('Unavailable')
                    ->sortable(),
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
                SelectFilter::make('is_base')
                    ->label('Base currency')
                    ->options([
                        '1' => 'Base only',
                        '0' => 'Non-base only',
                    ]),
            ])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingCurrencies::route('/'),
        ];
    }
}
