<?php

namespace SqlSync\FilamentSqlSync\Filament\Resources\AgentResource;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;
use SqlSync\LaravelSqlSync\Models\SyncAgent;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\Pages\ListAgents;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\Pages\ViewAgent;

class AgentResource extends Resource
{
    protected static ?string $model = SyncAgent::class;

    // No type hints — compatible with Filament v3/v4/v5
    protected static $navigationIcon = 'heroicon-o-computer-desktop';
    protected static $navigationSort = 2;

    protected static ?string $navigationLabel  = 'Agents';
    protected static ?string $modelLabel       = 'Agent';

    public static function getNavigationGroup(): ?string
    {
        return app(\SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin::class)->getNavigationGroup();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agent_id')
                    ->label('Agent ID')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Copied!'),

                TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->default('—'),

                IconColumn::make('is_online')
                    ->label('Online')
                    ->getStateUsing(fn ($record) => $record->isOnline())
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('last_heartbeat')
                    ->label('Last Heartbeat')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->since(),

                TextColumn::make('last_sync_at')
                    ->label('Last Sync')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->since(),

                TextColumn::make('total_synced')
                    ->label('Total Synced')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('company_id')
                    ->label('Company')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('last_heartbeat', 'desc')
            ->striped()
            ->poll('30s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Agent Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('agent_id')->label('Agent ID')->fontFamily('mono')->copyable(),
                        TextEntry::make('label')->label('Label')->default('Not set'),
                        TextEntry::make('company_id')->label('Company ID')->default('—'),
                        TextEntry::make('total_synced')->label('Total Records Synced')->numeric(),
                    ]),
                ]),

            Section::make('Activity')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('last_heartbeat')->label('Last Heartbeat')->dateTime()->since(),
                        TextEntry::make('last_sync_at')->label('Last Sync')->dateTime()->since(),
                        TextEntry::make('created_at')->label('First Seen')->dateTime(),
                        TextEntry::make('updated_at')->label('Last Updated')->dateTime(),
                    ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('meta')->label('Meta')->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgents::route('/'),
            'view'  => ViewAgent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
