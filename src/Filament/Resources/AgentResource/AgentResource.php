<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AgentResource;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\Pages\ListAgents;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\Pages\ViewAgent;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\SyncAgent;

class AgentResource extends Resource
{
    protected static ?string $model = SyncAgent::class;

    protected static ?string $navigationLabel = 'Agents';

    protected static ?string $modelLabel = 'Agent';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-computer-desktop';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        if ($fn = SqlSyncFilamentPlugin::get()->getAgentsQuery()) {
            $query = $fn($query);
        }

        return $query;
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
                    ->getStateUsing(fn (SyncAgent $record): bool => $record->isOnline())
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
            ->recordActions([ViewAction::make()])
            ->defaultSort('last_heartbeat', 'desc')
            ->striped()
            ->poll(config('sqlsync-filament.polling_interval', '30s'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Agent Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('agent_id')->label('Agent ID')->fontFamily('mono')->copyable(),
                        TextEntry::make('label')->label('Label')->default('Not set'),
                        TextEntry::make('company_id')->label('Company ID')->default('—'),
                        TextEntry::make('total_synced')->label('Total Records Synced')->numeric(),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Activity')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('last_heartbeat')->label('Last Heartbeat')->dateTime()->since(),
                        TextEntry::make('last_sync_at')->label('Last Sync')->dateTime()->since(),
                        TextEntry::make('created_at')->label('First Seen')->dateTime(),
                        TextEntry::make('updated_at')->label('Last Updated')->dateTime(),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('meta')->label('Meta')->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgents::route('/'),
            'view' => ViewAgent::route('/{record}'),
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
