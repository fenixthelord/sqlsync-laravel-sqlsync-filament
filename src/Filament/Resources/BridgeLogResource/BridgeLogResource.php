<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\BridgeLogResource;

use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use SqlSync\FilamentSqlSync\Filament\Resources\BridgeLogResource\Pages\ListBridgeLogs;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\BridgeLog;

class BridgeLogResource extends Resource
{
    protected static ?string $model = BridgeLog::class;

    protected static ?string $navigationLabel = 'Bridge Activity';

    protected static ?string $modelLabel = 'Bridge Log';

    protected static ?string $pluralModelLabel = 'Bridge Activity';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationGroup(): ?string
    {
        return SqlSyncFilamentPlugin::get()->getNavigationGroup();
    }

    public static function canViewAny(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized()
            && SqlSyncFilamentPlugin::get()->isFeatureEnabled('bridge');
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                TextColumn::make('record_name')
                    ->label('الصنف')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('match_value')
                    ->label('قيمة المطابقة')
                    ->copyable()
                    ->searchable(),

                BadgeColumn::make('action')
                    ->label('القرار')
                    ->colors([
                        'success' => 'created',
                        'info' => 'updated',
                        'warning' => 'skipped',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'أُنشئ',
                        'updated' => 'تحدّث',
                        'skipped' => 'اتخطّى',
                        default => $state,
                    }),

                BadgeColumn::make('reason')
                    ->label('السبب')
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'missing_match' => 'قيمة المطابقة فاضية',
                        'missing_defaults' => 'قيمة افتراضية ناقصة',
                        'db_error' => 'تعارض بقاعدة البيانات',
                        default => $state ?? '—',
                    })
                    ->visible(fn ($record) => $record?->action === 'skipped'),

                TextColumn::make('target_model')
                    ->label('الموديل الهدف')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('target_id')
                    ->label('المعرّف')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('القرار')
                    ->options([
                        'created' => 'أُنشئ',
                        'updated' => 'تحدّث',
                        'skipped' => 'اتخطّى',
                    ]),

                SelectFilter::make('reason')
                    ->label('السبب')
                    ->options([
                        'missing_match' => 'قيمة المطابقة فاضية',
                        'missing_defaults' => 'قيمة افتراضية ناقصة',
                        'db_error' => 'تعارض بقاعدة البيانات',
                    ]),
            ])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBridgeLogs::route('/'),
        ];
    }
}
