<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Jobs\ReapplyBridgeJob;
use SqlSync\LaravelSqlSync\Models\BridgeSetting;
use SqlSync\LaravelSqlSync\Models\SyncedRecord;
use SqlSync\LaravelSqlSync\Services\BridgeDryRunService;
use SqlSync\LaravelSqlSync\Services\StaleRecordsReportService;

class BridgeSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'sqlsync-bridge';

    protected static ?string $navigationLabel = 'Product Bridge';

    protected static ?string $title = 'ربط SqlSync بجدول المنتجات';

    protected string $view = 'sqlsync-filament::pages.bridge-settings';

    public ?array $data = [];

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationGroup(): ?string
    {
        return SqlSyncFilamentPlugin::get()->getNavigationGroup();
    }

    public static function canAccess(): bool
    {
        return SqlSyncFilamentPlugin::get()->isAuthorized()
            && SqlSyncFilamentPlugin::get()->isFeatureEnabled('bridge');
    }

    /**
     * Diagnoses the most common reason Product count stays at 0 despite
     * sqlsync_records having data — the exact confusion this method
     * exists to remove. Checked in priority order (most common /
     * cheapest-to-fix cause first), returns the FIRST applicable issue
     * rather than a wall of every possible problem at once.
     *
     * @return array{level: string, message: string}|null
     *                                                    null means no obvious problem detected.
     */
    /**
     * Proactively scans the target Product table's schema for columns
     * that WILL cause every single creation to fail — NOT NULL, no
     * database default, not auto-increment — and cross-references them
     * against everything the Bridge configuration currently covers
     * ('fields' targets, create_defaults keys, match_target,
     * category_target_field).
     *
     * This exists because of a real support session: the same class of
     * error (SQLSTATE 1364 'field X doesn't have a default value') got
     * discovered THREE separate times, one column at a time, only by
     * running a full 17k-row sync and reading exception text out of
     * Bridge Activity logs. That's a fundamentally reactive way to
     * find missing schema coverage — this audit answers the same
     * question upfront, before wasting a sync cycle on it.
     *
     * @return array<int, string> column names with no coverage — empty
     *                            array means the schema audit found nothing uncovered (though
     *                            this can't catch every possible failure mode, e.g. app-level
     *                            validation rules or non-NOT-NULL uniqueness constraints).
     */
    public function getRequiredColumnsAudit(): array
    {
        $setting = BridgeSetting::current();

        if (blank($setting->target_model) || ! class_exists((string) $setting->target_model)) {
            return [];
        }

        try {
            $modelClass = $setting->target_model;
            $model = new $modelClass;
            $table = $model->getTable();
            $connection = $model->getConnectionName() ?: config('database.default');

            $columns = DB::connection($connection)->select(
                'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()',
                [$table]
            );
        } catch (\Throwable) {
            // Can't introspect (unsupported DB driver, permissions,
            // whatever) — fail silently rather than break the settings
            // page over a nice-to-have audit.
            return [];
        }

        $covered = array_merge(
            array_keys($setting->fields ?? []),
            array_keys($setting->create_defaults ?? []),
            array_filter([$setting->match_target, $setting->category_target_field, $setting->auto_slug_column]),
            ['id', 'created_at', 'updated_at', 'deleted_at']
        );

        $missing = [];
        foreach ($columns as $col) {
            $isRequired = $col->IS_NULLABLE === 'NO'
                && $col->COLUMN_DEFAULT === null
                && ! str_contains((string) $col->EXTRA, 'auto_increment');

            if ($isRequired && ! in_array($col->COLUMN_NAME, $covered, true)) {
                $missing[] = $col->COLUMN_NAME;
            }
        }

        return $missing;
    }

    public function getDiagnosis(): ?array
    {
        $setting = BridgeSetting::current();
        $recordCount = SyncedRecord::count();

        if ($recordCount === 0) {
            // Nothing synced yet at all — not a Bridge config problem,
            // just means the Agent hasn't pushed anything. Don't alarm
            // the admin about Bridge settings when this is the real gap.
            return null;
        }

        if (! $setting->enabled) {
            return [
                'level' => 'danger',
                'message' => "عندك {$recordCount} سجل متزامَن، بس الربط التلقائي مو مفعّل — لهيك صفر منتجات. فعّل \"تفعيل الربط التلقائي\" فوق واحفظ، وبعدها اضغط \"إعادة التطبيق\" تحت.",
            ];
        }

        if (blank($setting->target_model) || ! class_exists((string) $setting->target_model)) {
            return [
                'level' => 'danger',
                'message' => "عندك {$recordCount} سجل متزامَن، بس اسم موديل المنتج (target_model) فاضي أو غلط. تأكد إنك كاتب المسار الكامل الصحيح، متل App\\Models\\Product.",
            ];
        }

        if (blank($setting->match_source) || blank($setting->match_target)) {
            return [
                'level' => 'warning',
                'message' => "عندك {$recordCount} سجل متزامَن، بس عمود المطابقة الرئيسي (Barcode عادةً) مو محدد بالكامل. كمّل قسم \"عمود المطابقة\" تحت.",
            ];
        }

        // Everything LOOKS configured — check if products actually exist.
        $modelClass = $setting->target_model;
        try {
            $productCount = $modelClass::count();
        } catch (\Throwable) {
            return [
                'level' => 'warning',
                'message' => 'ما قدرنا نتحقق من عدد المنتجات — تأكد إن target_model صحيح وجدوله موجود بقاعدة البيانات.',
            ];
        }

        if ($productCount === 0) {
            return [
                'level' => 'warning',
                'message' => 'الإعدادات كلها موجودة، بس لسا صفر منتجات فعلياً. الأرجح إنه ما ترتبط ربط بعد على البيانات الموجودة — اضغط "إعادة التطبيق" تحت هالصفحة. لو ضلت صفر بعدها، افتح Bridge Activity وشوف الأسباب (Skipped / missing_match عادةً يعني الحقل يلي بتطابق فيه فاضي بمعظم السجلات).',
            ];
        }

        return null;
    }

    public function mount(): void
    {
        $setting = BridgeSetting::current();

        $this->form->fill([
            'enabled' => $setting->enabled,
            'target_model' => $setting->target_model,
            'match_source' => $setting->match_source,
            'match_target' => $setting->match_target,
            'auto_slug_column' => $setting->auto_slug_column,
            'fallback_match_fields' => $setting->fallback_match_fields ?? [],
            'fields' => collect($setting->fields ?? [])
                ->map(fn ($source, $target) => ['target' => $target, 'source' => $source])
                ->values()
                ->all(),
            'create_defaults' => $setting->create_defaults ?? [],
            'skip_create_if_missing_defaults' => $setting->skip_create_if_missing_defaults,
            'category_model' => $setting->category_model,
            'category_source' => $setting->category_source,
            'category_use_tree_resolution' => $setting->category_use_tree_resolution ?? false,
            'category_match_column' => $setting->category_match_column,
            'category_target_field' => $setting->category_target_field,
            'category_slug_column' => $setting->category_slug_column,
        ]);
    }

    /**
     * Latest SyncedRecord — used as the "worked example" data set the
     * user sees while configuring mappings. Null means no sync has
     * happened yet, in which case the Blade view shows a friendly
     * "connect the Agent first" hint instead of an empty preview.
     */
    public function getSampleRecord(): ?SyncedRecord
    {
        return SyncedRecord::query()
            ->latest('synced_at')
            ->first();
    }

    /**
     * Flattens the latest record into dot-notation paths -> sample values.
     *
     * Example output:
     *   [
     *     'name'                 => 'Panadol 500mg',
     *     'quantity'             => 42,
     *     'extra_data.sel_price' => 150,
     *     'extra_data.price_4'   => 145,
     *     ...
     *   ]
     *
     * Drives BOTH the sample-data table AND the Select dropdowns on
     * the mapping fields — so the options the user picks from are
     * exactly the paths in the sample they see above.
     */
    public function getAvailablePaths(): array
    {
        $record = $this->getSampleRecord();
        if (! $record) {
            return [];
        }

        return $this->flattenRecordPaths($record->toArray());
    }

    private function flattenRecordPaths(array $data, string $prefix = ''): array
    {
        // Internal columns the user shouldn't be mapping FROM
        $skip = ['id', 'created_at', 'updated_at', 'agent_id', 'preset', 'company_id'];
        $out = [];

        foreach ($data as $key => $value) {
            if ($prefix === '' && in_array($key, $skip, true)) {
                continue;
            }

            $path = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (is_array($value) && ! empty($value) && ! array_is_list($value)) {
                $out = array_merge($out, $this->flattenRecordPaths($value, $path));

                continue;
            }

            $out[$path] = $value;
        }

        return $out;
    }

    /**
     * Formats options for the Select dropdowns as "path (= sample value)"
     * so the user sees the actual data they'd pull without cross-referencing
     * the preview table above.
     */
    private function pathOptions(): array
    {
        $paths = $this->getAvailablePaths();
        $out = [];

        foreach ($paths as $path => $value) {
            $sample = $this->formatSampleValue($value);
            $out[$path] = $sample === null ? $path : "{$path}  (= {$sample})";
        }

        return $out;
    }

    private function formatSampleValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            $s = (string) $value;

            return mb_strlen($s) > 30 ? mb_substr($s, 0, 30).'…' : $s;
        }

        return null;
    }

    public function form(Schema $schema): Schema
    {
        // Options list is computed ONCE per form render. If sync produces
        // new fields (e.g. a preset update adds price_36 next week), the
        // user just reloads this page to see them.
        $pathOptions = $this->pathOptions();
        $hasData = ! empty($pathOptions);

        return $schema->components([
            Section::make('التفعيل والموديل الهدف')
                ->description('حدد موديل المنتج الخاص بمشروعك — كل مشروع بيقدر يكون مختلف كلياً.')
                ->schema([
                    Toggle::make('enabled')
                        ->label('تفعيل الربط التلقائي')
                        ->helperText('عند التفعيل، أي عنصر يتزامن من الأمين/البيان بينحدّث تلقائياً بجدول منتجاتك.'),

                    TextInput::make('target_model')
                        ->label('اسم الـ Model بالكامل (Fully Qualified Class Name)')
                        ->placeholder('App\\Models\\Product')
                        ->required()
                        ->helperText('مثال: App\\Models\\Product'),
                ])
                ->columns(1),

            Section::make('عمود المطابقة')
                ->description('كيف بنعرف إنو هاد الصنف موجود أصلاً بجدولك؟')
                ->schema([
                    // Was TextInput — user had to memorize field names. Now Select
                    // with real paths from the actual last-synced record.
                    Select::make('match_source')
                        ->label('الحقل بالسجل المتزامَن')
                        ->options($pathOptions)
                        ->searchable()
                        ->native(false)
                        ->helperText($hasData
                            ? 'اختر الحقل — القيمة يمين اسم الحقل هي عيّنة من آخر سجل مزامَن.'
                            : '⚠ لا يوجد بيانات مزامنة بعد. اربط الوكيل وقم بأول مزامنة، ثم ارجع لهذه الصفحة لتشوف الحقول المتاحة.')
                        ->required(),

                    TextInput::make('match_target')
                        ->label('العمود بجدولك')
                        ->placeholder('sku')
                        ->helperText('اسم العمود في جدول المنتجات عندك (مثلاً barcode أو sku)')
                        ->required(),
                ])
                ->columns(2),

            Section::make('توليد Slug تلقائي وآمن (اختياري لكن موصى فيه بشدة)')
                ->description('لا تربط عمود slug مباشرة بحقل من البيانات المتزامنة (مثل code) — هاد الحقل غالباً فاضي لكتير أصناف أو مش unique، وبيسبب فشل إنشاء كل منتج بهالحالة (Column slug cannot be null / Duplicate entry). بدل هيك، فعّل هالخيار: بيولّد slug تلقائياً من اسم الصنف + معرّف فريد داخلي — مضمون 100% إنه مش فاضي ومش مكرر أبداً.')
                ->schema([
                    TextInput::make('auto_slug_column')
                        ->label('عمود الـ slug بجدولك')
                        ->placeholder('slug')
                        ->helperText('لو حاطط "slug" هون بردو بقسم "تعيين الحقول" تحت، هالإعداد بيفوز دايماً — ما تحتاج تحذفه من هناك يدوياً.'),
                ]),

            Section::make('مطابقة احتياطية (اختياري) — للأصناف بدون باركود')
                ->description('لو الصنف ما عنده باركود (شائع بمواد صحية/طبية بتباع بالاسم بس)، الحقل يلي فوق (عمود المطابقة الرئيسي) بيضل فاضي وينتحجب الصنف بالكامل. هون تقدر تحدد مطابقة بديلة — مثلاً "الاسم + الماركة" — تستخدم بس لما الحقل الرئيسي فاضي. كل الحقول يلي تحددها هون لازم تتطابق مع بعضها (AND) عشان تعتبر نفس الصنف.')
                ->schema([
                    Repeater::make('fallback_match_fields')
                        ->label('')
                        ->schema([
                            TextInput::make('target')
                                ->label('العمود بجدولك')
                                ->placeholder('name')
                                ->helperText('مثلاً name أو brand')
                                ->required(),

                            Select::make('source')
                                ->label('الحقل بالسجل المتزامَن')
                                ->options($pathOptions)
                                ->searchable()
                                ->native(false)
                                ->helperText($hasData
                                    ? 'القيمة بجانب الحقل عيّنة حقيقية من آخر مزامنة'
                                    : '⚠ لا يوجد بيانات بعد')
                                ->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('إضافة حقل للمطابقة الاحتياطية')
                        ->reorderable(false)
                        ->helperText('مثال شائع للصيدليات: name ← name  و  brand ← extra_data.origin')
                        ->itemLabel(function (array $state): ?string {
                            if (! filled($state['target'] ?? null) || ! filled($state['source'] ?? null)) {
                                return null;
                            }

                            return $state['target'].' = '.$state['source'];
                        }),
                ]),

            Section::make('تعيين الحقول (Field Mapping)')
                ->description('أي أعمدة بجدولك بدك تنحدّث تلقائياً، ومن وين تجيب قيمتها. القيمة المعروضة بجانب الحقل هي القيمة الفعلية من آخر مزامنة — بتقدر تتأكد إنك عم تربط الحقل الصح قبل ما تحفظ.')
                ->schema([
                    Repeater::make('fields')
                        ->label('')
                        ->schema([
                            TextInput::make('target')
                                ->label('العمود بجدولك')
                                ->placeholder('price')
                                ->helperText('اسم العمود في جدول المنتجات عندك')
                                ->required(),

                            Select::make('source')
                                ->label('الحقل بالسجل المتزامَن')
                                ->options($pathOptions)
                                ->searchable()
                                ->native(false)
                                ->helperText($hasData
                                    ? 'القيمة بجانب الحقل عيّنة حقيقية من آخر مزامنة'
                                    : '⚠ لا يوجد بيانات بعد — اربط الوكيل أولاً')
                                ->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('إضافة حقل')
                        ->reorderable(false)
                        // Nice collapsed label so the user can see all their
                        // mappings at a glance: "price ← extra_data.sel_price"
                        ->itemLabel(function (array $state): ?string {
                            if (! filled($state['target'] ?? null) || ! filled($state['source'] ?? null)) {
                                return null;
                            }

                            return $state['target'].' ← '.$state['source'];
                        }),
                ]),

            Section::make('التصنيف التلقائي (اختياري)')
                ->description('لو صنف جديد جاي بفئة (مثل "أدوات وصيانة") مش موجودة بجدول الفئات عندك، بتنشأ تلقائياً وبتترابط بالمنتج — بدل ما تعلّق عملية الإنشاء بسبب حقل category_id الإجباري.')
                ->schema([
                    TextInput::make('category_model')
                        ->label('اسم الـ Model بالكامل')
                        ->placeholder('App\\Models\\Category'),

                    Select::make('category_source')
                        ->label('الحقل بالسجل المتزامَن')
                        ->options($pathOptions)
                        ->searchable()
                        ->native(false)
                        ->placeholder('اختر الحقل يلي بيحدد اسم الفئة')
                        ->helperText('لبرنامج البيان: هاد الحقل هو group_guid (رقم التصنيف الشجري الخام — مش اسم مباشر).'),

                    Toggle::make('category_use_tree_resolution')
                        ->label('التصنيف شجري (Tree) مو اسم مباشر')
                        ->helperText('فعّلها لو الحقل يلي فوق رقم من شجرة تصنيف هرمية (مثل TreeNum بالبيان: 117185) بدل اسم صريح. النظام رح يدور تلقائياً على أقرب تصنيف أب مطابق بالشجرة المتزامنة (117 → اسم التصنيف)، ويستخدم اسمه الحقيقي بدل الرقم الخام.'),

                    TextInput::make('category_match_column')
                        ->label('العمود بجدول الفئات للمطابقة/الإنشاء')
                        ->placeholder('name'),

                    TextInput::make('category_target_field')
                        ->label('العمود بجدول المنتجات يلي بياخد معرّف الفئة')
                        ->placeholder('category_id'),

                    TextInput::make('category_slug_column')
                        ->label('عمود الـ slug بجدول الفئات (اتركه فاضي إذا مافي)')
                        ->placeholder('slug'),
                ])
                ->columns(2),

            Section::make('قيم افتراضية عند إنشاء منتج جديد')
                ->description('لو الصنف مش موجود أصلاً بجدولك، وعندك أعمدة إجبارية (متل category_id)، حدد قيمة افتراضية هون. اتركها فاضية إذا ما في قيمة آمنة — هيك ما رح ينعمل إنشاء تلقائي وبتضل تراجعه يدوياً.')
                ->schema([
                    KeyValue::make('create_defaults')
                        ->label('')
                        ->keyLabel('العمود')
                        ->valueLabel('القيمة الافتراضية')
                        ->addActionLabel('إضافة قيمة افتراضية'),

                    Toggle::make('skip_create_if_missing_defaults')
                        ->label('تجاهل الإنشاء التلقائي إذا نقصت قيمة افتراضية إجبارية')
                        ->default(true),
                ]),
        ])->statePath('data');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('حفظ الإعدادات')
                ->submit('save'),
        ];
    }

    public ?array $dryRunResults = null;

    public function runDryRun(): void
    {
        // Uses the CURRENTLY SAVED settings (whatever's in the DB right
        // now) rather than $this->data — the admin should save any
        // pending edits first so the dry run reflects reality, not a
        // half-edited form state.
        $this->dryRunResults = app(BridgeDryRunService::class)->run(20);
    }

    public function reapplyToExisting(): void
    {
        $setting = BridgeSetting::current();

        ReapplyBridgeJob::dispatch($setting->company_id);

        Notification::make()
            ->title('بدأت المعالجة بالخلفية')
            ->body('رح يتحدّث التقدم تلقائياً تحت. تقدر تسكر الصفحة وترجعلها لاحقاً، العملية مستمرة بالخلفية.')
            ->success()
            ->send();
    }

    public function getReapplyProgress(): ?array
    {
        $setting = BridgeSetting::current();
        $key = ReapplyBridgeJob::cacheKey($setting->company_id);

        return Cache::get($key);
    }

    /**
     * Report-only — deletion candidates for human review. See
     * StaleRecordsReportService's docblock for exactly why this is
     * ONLY trustworthy after a genuine Force Full Resync, and why an
     * ordinary incremental sync cycle can never distinguish "quietly
     * unchanged" from "deleted at the source". Nothing here ever
     * deletes or deactivates a product automatically — by explicit
     * decision, that's too dangerous to automate (a flagged item could
     * be referenced by an existing order, a bundle, anything).
     */
    public function getStaleRecordsReport(): array
    {
        $setting = BridgeSetting::current();

        return app(StaleRecordsReportService::class)->report($setting->company_id);
    }

    /**
     * Live preview: for each currently-configured mapping, what value
     * would actually get written to the product table if we bridged
     * the sample record right now.
     *
     * Rendered by the Blade view below the form. The user watches this
     * table update as they edit — no save + reapply + refresh + peek
     * at the DB dance anymore.
     *
     * @return array<int, array{target: string, source: string, value: string, ok: bool}>
     */
    public function getMappingPreview(): array
    {
        $record = $this->getSampleRecord();
        if (! $record) {
            return [];
        }

        $recordArray = $record->toArray();
        $fields = $this->data['fields'] ?? [];

        $preview = [];
        foreach ($fields as $mapping) {
            $target = $mapping['target'] ?? null;
            $source = $mapping['source'] ?? null;
            if (! $target || ! $source) {
                continue;
            }

            $value = Arr::get($recordArray, $source);
            $preview[] = [
                'target' => $target,
                'source' => $source,
                'value' => $this->formatSampleValue($value) ?? '(فاضي)',
                'ok' => $value !== null && $value !== '',
            ];
        }

        return $preview;
    }

    /**
     * One-click starting point for Al-Bayan pharmacy customers — fills
     * in every SOURCE-side value we've already confirmed empirically
     * (barcode as primary match, name+brand as fallback for barcode-less
     * items, group_guid + tree resolution for categories). Leaves
     * target_model, match_target, and the target columns in 'fields'
     * blank/untouched, since those are specific to each project's own
     * Product schema and can't be guessed safely.
     *
     * This doesn't eliminate configuration — it removes the part that
     * required knowing Al-Bayan's internal data shape (which even the
     * developer had to reverse-engineer from the live database), and
     * leaves only the part that's inherently project-specific: what your
     * own Product table's columns are called.
     */
    public function applyAlBayanPharmacyDefaults(): void
    {
        $this->form->fill(array_merge($this->data, [
            'match_source' => 'barcode',
            'fallback_match_fields' => [
                ['target' => 'name', 'source' => 'name'],
                ['target' => 'brand', 'source' => 'extra_data.origin'],
            ],
            'category_source' => 'group_guid',
            'category_use_tree_resolution' => true,
        ]));

        Notification::make()
            ->title('تم تعبئة القيم المعروفة من البيان')
            ->body('باقي عليك بس: target_model، match_target، وأعمدة الحقول بقسم "تعيين الحقول" — هاي بتعتمد على جدول المنتجات عندك تحديداً.')
            ->success()
            ->send();
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $fields = collect($state['fields'] ?? [])
            ->filter(fn ($row) => filled($row['target'] ?? null))
            ->mapWithKeys(fn ($row) => [$row['target'] => $row['source']])
            ->all();

        $fallbackMatchFields = collect($state['fallback_match_fields'] ?? [])
            ->filter(fn ($row) => filled($row['target'] ?? null) && filled($row['source'] ?? null))
            ->values()
            ->all();

        $setting = BridgeSetting::current();
        $setting->update([
            'enabled' => $state['enabled'] ?? false,
            'target_model' => $state['target_model'] ?? null,
            'match_source' => $state['match_source'] ?? null,
            'match_target' => $state['match_target'] ?? null,
            'auto_slug_column' => $state['auto_slug_column'] ?? null,
            'fallback_match_fields' => $fallbackMatchFields,
            'fields' => $fields,
            'create_defaults' => $state['create_defaults'] ?? [],
            'skip_create_if_missing_defaults' => $state['skip_create_if_missing_defaults'] ?? true,
            'category_model' => $state['category_model'] ?? null,
            'category_source' => $state['category_source'] ?? null,
            'category_use_tree_resolution' => $state['category_use_tree_resolution'] ?? false,
            'category_match_column' => $state['category_match_column'] ?? null,
            'category_target_field' => $state['category_target_field'] ?? null,
            'category_slug_column' => $state['category_slug_column'] ?? null,
        ]);

        Notification::make()
            ->title('تم حفظ إعدادات الربط')
            ->success()
            ->send();
    }
}
