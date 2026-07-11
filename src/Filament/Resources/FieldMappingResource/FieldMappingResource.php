<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\Pages\CreateFieldMapping;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\Pages\EditFieldMapping;
use SqlSync\FilamentSqlSync\Filament\Resources\FieldMappingResource\Pages\ListFieldMappings;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\FieldMapping;

class FieldMappingResource extends Resource
{
    protected static ?string $model = FieldMapping::class;

    protected static ?string $navigationLabel = 'Field Mappings';

    protected static ?string $modelLabel = 'Field Mapping';

    protected static ?string $pluralModelLabel = 'Field Mappings';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationGroup(): ?string
    {
        return SqlSyncFilamentPlugin::get()->getNavigationGroup();
    }

    public static function canViewAny(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public static function canView($record): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public static function canCreate(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public static function canEdit($record): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public static function canDelete($record): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if ($fn = SqlSyncFilamentPlugin::get()->getMappingsQuery()) {
            $query = $fn($query);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $presetOptions = self::presetOptions();
        $roleOptions = self::roleOptions();

        return $schema->components([
            Section::make('Field Mapping')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('preset')
                            ->label('Preset')
                            ->options($presetOptions)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('source_field', null)),

                        Select::make('source_field')
                            ->label('Source Field')
                            ->options(fn ($get) => self::sourceFieldOptions($get('preset') ?? 'al_ameen'))
                            ->required()
                            ->searchable(),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('target_label')
                            ->label('Label (shown to user)')
                            ->placeholder('سعر المفرق')
                            ->required()
                            ->maxLength(100),

                        Select::make('target_role')
                            ->label('Role')
                            ->options($roleOptions)
                            ->searchable()
                            ->nullable(),
                    ]),

                    Grid::make(3)->schema([
                        Toggle::make('is_price')
                            ->label('Is Price')
                            ->default(false),

                        Toggle::make('is_unit')
                            ->label('Is Unit')
                            ->default(false),

                        Toggle::make('is_visible')
                            ->label('Visible in App')
                            ->default(true),
                    ]),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('preset')
                    ->label('Preset')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'al_ameen' => 'success',
                        'al_bayan' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('source_field')
                    ->label('Source Field')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('target_label')
                    ->label('Label')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('target_role')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->default('—'),

                IconColumn::make('is_price')
                    ->label('Price')
                    ->boolean()
                    ->trueIcon('heroicon-o-currency-dollar')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('is_unit')
                    ->label('Unit')
                    ->boolean()
                    ->trueIcon('heroicon-o-scale')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray'),

                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('preset')
                    ->options(self::presetOptions()),

                SelectFilter::make('is_price')
                    ->label('Type')
                    ->options([
                        '1' => 'Prices only',
                        '0' => 'Non-prices',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order')
            ->striped()
            ->emptyStateHeading('No mappings configured')
            ->emptyStateDescription('Add mappings to define how accounting fields appear in the app.')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add Mapping')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFieldMappings::route('/'),
            'create' => CreateFieldMapping::route('/create'),
            'edit' => EditFieldMapping::route('/{record}/edit'),
        ];
    }

    private static function presetOptions(): array
    {
        return collect(config('sqlsync.presets', []))
            ->mapWithKeys(fn ($class, $key) => [$key => ucwords(str_replace('_', ' ', $key))])
            ->toArray() ?: [
                'al_ameen' => 'Al-Ameen (الأمين)',
                'al_bayan' => 'Al-Bayan (البيان)',
            ];
    }

    private static function sourceFieldOptions(string $preset): array
    {
        return match ($preset) {
            'al_ameen' => [
                'mtHigh' => 'mtHigh — سعر عالي',
                'mtLow' => 'mtLow — سعر منخفض',
                'mtWhole' => 'mtWhole — سعر الجملة',
                'mtHalf' => 'mtHalf — سعر نصف جملة',
                'mtRetail' => 'mtRetail — سعر المفرق',
                'mtEndUser' => 'mtEndUser — سعر المستهلك',
                'mtExport' => 'mtExport — سعر التصدير',
                'mtVendor' => 'mtVendor — سعر المورد',
                'mtUnity' => 'mtUnity — الوحدة الأساسية',
                'mtUnit2' => 'mtUnit2 — الوحدة الثانية',
                'mtUnit2Fact' => 'mtUnit2Fact — معامل الوحدة 2',
                'mtUnit3' => 'mtUnit3 — الوحدة الثالثة',
                'mtUnit3Fact' => 'mtUnit3Fact — معامل الوحدة 3',
                'mtOrigin' => 'mtOrigin — المنشأ',
                'mtPriceType' => 'mtPriceType — نوع السعر',
                'mtSellType' => 'mtSellType — نوع البيع',
            ],
            // Was completely empty until now — every Al-Bayan customer
            // (Saati Pharmacy, Alaa Pharmacy, any future one) opening
            // this page got a blank Source Field dropdown with no way
            // to add a single mapping. Field names match extra_data's
            // actual keys as produced by AlBayanPreset.cs on the Agent
            // side (see Presets/AlBayanPreset.cs) — sel_price/regular_
            // price/price_1..35/etc.
            'al_bayan' => [
                'sel_price' => 'sel_price — سعر البيع',
                'regular_price' => 'regular_price — السعر الاعتيادي',
                'cost_price' => 'cost_price — سعر التكلفة',
                'price_1' => 'price_1 — سعر 1',
                'price_2' => 'price_2 — سعر 2',
                'price_3' => 'price_3 — سعر 3',
                'price_4' => 'price_4 — سعر 4',
                'price_5' => 'price_5 — سعر 5',
                'price_21' => 'price_21 — سعر 21',
                'price_22' => 'price_22 — سعر 22',
                'price_23' => 'price_23 — سعر 23',
                'price_24' => 'price_24 — سعر 24',
                'price_25' => 'price_25 — سعر 25',
                'price_31' => 'price_31 — سعر 31',
                'price_32' => 'price_32 — سعر 32',
                'price_33' => 'price_33 — سعر 33',
                'price_34' => 'price_34 — سعر 34',
                'price_35' => 'price_35 — سعر 35',
                'price_last' => 'price_last — آخر سعر',
                'origin' => 'origin — المنشأ / الماركة',
                'group_guid' => 'group_guid — رقم التصنيف الشجري الخام',
            ],
            default => [],
        };
    }

    private static function roleOptions(): array
    {
        return [
            'retail_price' => 'retail_price — سعر المفرق',
            'wholesale_price' => 'wholesale_price — سعر الجملة',
            'half_price' => 'half_price — نصف جملة',
            'end_user_price' => 'end_user_price — سعر المستهلك',
            'high_price' => 'high_price — سعر عالي',
            'low_price' => 'low_price — سعر منخفض',
            'export_price' => 'export_price — سعر التصدير',
            'vendor_price' => 'vendor_price — سعر المورد',
            'unit_1' => 'unit_1 — الوحدة الأساسية',
            'unit_2' => 'unit_2 — الوحدة الثانية',
            'unit_2_factor' => 'unit_2_factor — معامل الوحدة 2',
            'unit_3' => 'unit_3 — الوحدة الثالثة',
            'unit_3_factor' => 'unit_3_factor — معامل الوحدة 3',
        ];
    }
}
