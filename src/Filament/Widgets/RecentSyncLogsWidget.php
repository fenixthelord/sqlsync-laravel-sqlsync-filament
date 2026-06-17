<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\SyncLog;

class RecentSyncLogsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Sync Activity';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    public function table(Table $table): Table
    {
        $plugin = SqlSyncFilamentPlugin::get();
        $limit  = (int) config('sqlsync-filament.recent_logs_limit', 20);
        $query  = SyncLog::query()->orderByDesc('synced_at')->limit($limit);

        if ($fn = $plugin->getLogsQuery()) {
            $query = $fn($query);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('agent_id')
                    ->label('Agent')
                    ->fontFamily('mono')
                    ->limit(20),

                TextColumn::make('preset')
                    ->label('Preset')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
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
                    ->color(fn (string $state): string => match ($state) {
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
            ->poll(config('sqlsync-filament.polling_interval', '30s') ?: null);
    }
}
