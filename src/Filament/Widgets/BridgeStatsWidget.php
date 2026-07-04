<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\BridgeLog;

class BridgeStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized();
    }

    protected function getStats(): array
    {
        $today = BridgeLog::whereDate('created_at', today());

        return [
            Stat::make('أُنشئ اليوم', number_format((clone $today)->where('action', 'created')->count()))
                ->description('منتجات جديدة كلياً')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('success')
                ->icon('heroicon-o-sparkles'),

            Stat::make('تحدّث اليوم', number_format((clone $today)->where('action', 'updated')->count()))
                ->description('سعر/كمية محدّثة على منتج موجود')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->icon('heroicon-o-pencil-square'),

            Stat::make('اتخطّى اليوم', number_format((clone $today)->where('action', 'skipped')->count()))
                ->description('محتاج مراجعة يدوية')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->icon('heroicon-o-no-symbol'),

            Stat::make('الإجمالي منذ البداية', number_format(BridgeLog::count()))
                ->description('كل القرارات المسجّلة')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray')
                ->icon('heroicon-o-circle-stack'),
        ];
    }
}
