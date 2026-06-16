<?php

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use SqlSync\LaravelSqlSync\Models\SyncLog;

class RecentSyncLogsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Sync Activity';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(SyncLog::query()->orderByDesc('synced_at')->limit(20))
            ->columns([
                TextColumn::make('agent_id')
                    ->label('Agent')
                    ->fontFamily('mono')
                    ->limit(20),

                TextColumn::make('preset')
                    ->label('Preset')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'al_ameen' => 'success',
                        'al_bayan' => 'warning',
                        default    => 'gray',
                    }),

                TextColumn::make('inserted')
                    ->label('Inserted')
                    ->badge()
                    ->color('success'),

                TextColumn::make('updated')
                    ->label('Updated')
                    ->badge()
                    ->color('info'),

                TextColumn::make('skipped')
                    ->label('Skipped')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'success' => 'success',
                        'error'   => 'danger',
                        default   => 'gray',
                    }),

                TextColumn::make('synced_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->since(),
            ])
            ->paginated(false)
            ->poll('30s');
    }
}
