<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\BridgeLog;
use SqlSync\LaravelSqlSync\Models\BridgeSetting;
use SqlSync\LaravelSqlSync\Models\CategoryNode;
use SqlSync\LaravelSqlSync\Models\SyncedRecord;
use SqlSync\LaravelSqlSync\Models\SyncLog;

/**
 * "Danger Zone" — wipes SqlSync's own data so testing / a customer
 * database rebuild can start completely fresh.
 *
 * Deliberately NOT reachable from the Windows Agent: the Agent has no
 * direct database access to begin with (it only talks to the sync API),
 * and a destructive whole-database wipe belongs behind an admin panel
 * with a session, permissions, and a confirmation step — not a button
 * in a tray app that syncs quietly in the background. The Agent's own
 * "Force Full Resync" button is a SEPARATE, non-destructive action (it
 * only clears locally-cached watermarks so the next cycle re-sends
 * everything) — this page is the one that actually deletes rows.
 *
 * Hidden by default (feature flag 'reset', off unless explicitly
 * enabled) since most installations should never need it after initial
 * setup.
 */
class ResetPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'sqlsync-reset';

    protected static ?string $navigationLabel = 'Danger Zone';

    protected static ?string $title = 'إعادة تعيين SqlSync';

    protected string $view = 'sqlsync-filament::pages.reset';

    public ?array $data = [];

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationSort(): ?int
    {
        return 99; // always last in the group
    }

    public static function getNavigationGroup(): ?string
    {
        return SqlSyncFilamentPlugin::get()->getNavigationGroup();
    }

    public static function canAccess(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized()
            && SqlSyncFilamentPlugin::get()->isFeatureEnabled('reset');
    }

    public function mount(): void
    {
        $this->form->fill([
            'confirm_phrase' => '',
            'also_delete_products' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $setting = BridgeSetting::current();
        $targetModel = $setting->target_model;

        return $schema->components([
            Section::make('شو رح ينمسح')
                ->description($this->describeScope())
                ->schema([]),

            Section::make('حذف المنتجات أيضاً (اختياري، أخطر)')
                ->description($targetModel
                    ? "لو فعّلتها، رح ينحذف كل صف من جدول المنتجات الفعلي عندك ({$targetModel}) — مو بس بيانات SqlSync الوسيطة. هاي بيانات حقيقية، احذر."
                    : 'ما فيه Product Bridge معرّف حالياً (target_model فاضي)، فهاد الخيار مش متاح.')
                ->schema([
                    Checkbox::make('also_delete_products')
                        ->label('نعم، احذف كل المنتجات من '.($targetModel ?? '(غير معرّف)').' كمان')
                        ->disabled(blank($targetModel)),
                ]),

            Section::make('التأكيد')
                ->description('للتأكيد، اكتب كلمة RESET بالحقل تحت (بأحرف كبيرة بالضبط)، وبعدها اضغط الزر الأحمر تحت.')
                ->schema([
                    TextInput::make('confirm_phrase')
                        ->label('اكتب RESET للتأكيد')
                        ->placeholder('RESET')
                        ->required()
                        ->live(),
                ]),
        ])->statePath('data');
    }

    private function describeScope(): string
    {
        $records = SyncedRecord::count();
        $logs = SyncLog::count();
        $bridgeLogs = BridgeLog::count();
        $categories = CategoryNode::count();

        return "هاد رح يمسح بشكل نهائي: {$records} سجل متزامَن (sqlsync_records)، "
            ."{$logs} سجل مزامنة (sqlsync_logs)، {$bridgeLogs} سجل نشاط ربط (sqlsync_bridge_logs)، "
            ."و{$categories} عقدة تصنيف (sqlsync_category_nodes). "
            .'إعدادات الـ Bridge نفسها (Product Bridge config) ما بتتحذف — تضل زي ما هي.';
    }

    public function getFormActions(): array
    {
        return [];
    }

    /**
     * Called from the Blade view's own button (not a standard Filament
     * form action) so we can gate it on the live confirm_phrase value
     * ourselves rather than relying on a generic submit action.
     */
    public function performReset(): void
    {
        $state = $this->form->getState();

        if (($state['confirm_phrase'] ?? '') !== 'RESET') {
            Notification::make()
                ->title('اكتب RESET بالضبط للتأكيد')
                ->danger()
                ->send();

            return;
        }

        $alsoDeleteProducts = (bool) ($state['also_delete_products'] ?? false);
        $setting = BridgeSetting::current();
        $targetModel = $setting->target_model;

        $counts = [
            'records' => SyncedRecord::count(),
            'logs' => SyncLog::count(),
            'bridge_logs' => BridgeLog::count(),
            'categories' => CategoryNode::count(),
        ];

        DB::transaction(function () use ($alsoDeleteProducts, $targetModel) {
            SyncedRecord::query()->delete();
            SyncLog::query()->delete();
            BridgeLog::query()->delete();
            CategoryNode::query()->delete();

            if ($alsoDeleteProducts && $targetModel && class_exists($targetModel)) {
                $targetModel::query()->delete();
            }
        });

        Log::warning('SqlSync: Danger Zone reset performed', [
            'user' => auth()->user()?->getAuthIdentifier(),
            'counts' => $counts,
            'also_deleted_products' => $alsoDeleteProducts,
            'target_model' => $targetModel,
        ]);

        $this->form->fill([
            'confirm_phrase' => '',
            'also_delete_products' => false,
        ]);

        Notification::make()
            ->title('تم التصفير')
            ->body(sprintf(
                'انمسح: %d سجل، %d سجل مزامنة، %d سجل ربط، %d تصنيف.%s',
                $counts['records'], $counts['logs'], $counts['bridge_logs'], $counts['categories'],
                $alsoDeleteProducts ? ' وتم حذف كل المنتجات أيضاً.' : ''
            ))
            ->warning()
            ->persistent()
            ->send();
    }
}
