<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\RecordResource;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\Pages\ListRecords;
use SqlSync\FilamentSqlSync\Filament\Resources\RecordResource\Pages\ViewRecord;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\BridgeSetting;
use SqlSync\LaravelSqlSync\Models\SyncedRecord;

class RecordResource extends Resource
{
    protected static ?string $model = SyncedRecord::class;

    protected static ?string $navigationLabel = 'Synced Records';

    protected static ?string $modelLabel = 'Record';

    protected static ?string $pluralModelLabel = 'Records';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-circle-stack';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if ($fn = SqlSyncFilamentPlugin::get()->getRecordsQuery()) {
            $query = $fn($query);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $presetOptions = collect(config('sqlsync.presets', []))
            ->mapWithKeys(fn ($class, $key) => [$key => ucwords(str_replace('_', ' ', $key))])
            ->toArray();

        if (empty($presetOptions)) {
            $presetOptions = [
                'al_ameen' => 'Al-Ameen (الأمين)',
                'al_bayan' => 'Al-Bayan (البيان)',
            ];
        }

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('latin_name')
                    ->label('Latin Name')
                    ->searchable()
                    ->toggleable()
                    ->color('gray'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('preset')
                    ->label('Preset')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'al_ameen' => 'success',
                        'al_bayan' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('group_name')
                    ->label('Group')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('unit')
                    ->label('Unit')
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('synced_at')
                    ->label('Last Sync')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('preset')->options($presetOptions),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('createProduct')
                    ->label('إنشاء منتج')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(function (SyncedRecord $record): bool {
                        $setting = BridgeSetting::current($record->company_id);

                        if (! $setting->target_model
                            || ! class_exists($setting->target_model)
                            || blank($setting->match_source)
                            || blank($setting->match_target)) {
                            return false;
                        }

                        $matchValue = Arr::get($record->toArray(), $setting->match_source);

                        if (blank($matchValue)) {
                            return false;
                        }

                        // Hide once a matching product already exists —
                        // this is only for records the auto-bridge (or a
                        // previous manual click) hasn't created yet.
                        return ! $setting->target_model::where($setting->match_target, $matchValue)->exists();
                    })
                    ->form(function (SyncedRecord $record): array {
                        $setting = BridgeSetting::current($record->company_id);
                        $recordArray = $record->toArray();

                        $fields = [
                            TextInput::make('__match_value')
                                ->label('قيمة '.$setting->match_target)
                                ->default(Arr::get($recordArray, $setting->match_source))
                                ->required(),
                        ];

                        foreach (($setting->fields ?? []) as $targetColumn => $sourceField) {
                            $fields[] = TextInput::make($targetColumn)
                                ->label($targetColumn)
                                ->default(Arr::get($recordArray, $sourceField));
                        }

                        $fields[] = KeyValue::make('extra')
                            ->label('حقول إضافية (مثل category_id) — مطلوبة حسب جدولك')
                            ->keyLabel('العمود')
                            ->valueLabel('القيمة')
                            ->addActionLabel('إضافة حقل');

                        return $fields;
                    })
                    ->action(function (SyncedRecord $record, array $data): void {
                        $setting = BridgeSetting::current($record->company_id);
                        $modelClass = $setting->target_model;

                        $payload = collect($data)
                            ->except(['__match_value', 'extra'])
                            ->merge($data['extra'] ?? [])
                            ->filter(fn ($value) => filled($value))
                            ->all();

                        $payload[$setting->match_target] = $data['__match_value'];

                        $modelClass::create($payload);

                        Notification::make()
                            ->title('تم إنشاء المنتج بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Information')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')->label('Name')->weight('bold'),
                        TextEntry::make('latin_name')->label('Latin Name'),
                        TextEntry::make('preset')->label('Preset')->badge(),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('code')->label('Code'),
                        TextEntry::make('barcode')->label('Barcode'),
                        TextEntry::make('unit')->label('Unit'),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('group_name')->label('Group'),
                        TextEntry::make('quantity')->label('Quantity')->numeric(),
                        TextEntry::make('is_active')->label('Active')
                            ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Pricing & Extra Data')
                ->schema([
                    KeyValueEntry::make('extra_data')->label('Extra Data')->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Section::make('Sync Info')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('agent_id')->label('Agent ID'),
                        TextEntry::make('synced_at')->label('Last Sync')->dateTime(),
                        TextEntry::make('source_guid')->label('Source GUID'),
                    ]),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'view' => ViewRecord::route('/{record}'),
        ];
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
}
