<?php

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use SqlSync\LaravelSqlSync\Models\SyncAgent;

class AgentsOnlineWidget extends BaseWidget
{
    protected static ?string $heading = 'Agents Status';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(SyncAgent::query()->orderByDesc('last_heartbeat'))
            ->columns([
                TextColumn::make('agent_id')
                    ->label('Agent ID')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('label')
                    ->label('Label')
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
                    ->label('Last Seen')
                    ->since()
                    ->sortable(),

                TextColumn::make('total_synced')
                    ->label('Records Synced')
                    ->numeric()
                    ->badge()
                    ->color('success'),
            ])
            ->paginated(false)
            ->poll('30s');
    }
}
