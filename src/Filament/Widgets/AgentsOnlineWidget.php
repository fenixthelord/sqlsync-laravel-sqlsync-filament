<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\SyncAgent;

class AgentsOnlineWidget extends BaseWidget
{
    protected static ?string $heading = 'Agents Status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public function table(Table $table): Table
    {
        $plugin = SqlSyncFilamentPlugin::get();
        $query  = SyncAgent::query()->orderByDesc('last_heartbeat');

        if ($fn = $plugin->getAgentsQuery()) {
            $query = $fn($query);
        }

        return $table
            ->query($query)
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
                    ->getStateUsing(fn (SyncAgent $record): bool => $record->isOnline())
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
            ->poll(config('sqlsync-filament.polling_interval', '30s') ?: null);
    }
}
