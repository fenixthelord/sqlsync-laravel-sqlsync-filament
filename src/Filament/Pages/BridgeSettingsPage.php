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
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\FilamentSqlSync\Support\SchemaIntrospector;
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
            array_filter([$setting->match_target, $setting->category_target_field, $setting->auto_slug_column, $setting->source_number_column]),
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

    /**
     * Checks whether the configured source_number_column actually
     * exists on the target table — not just whether the admin typed
     * something into the field. This is the strongest identity layer
     * the Bridge offers, so it's enforced as a hard requirement (see
     * save()) rather than left as an easy-to-skip optional setting.
     *
     * @return array{configured: bool, exists: bool, table: ?string, create_sql: ?string}
     */
    /**
     * Real models found in app/Models — powers the smart target_model
     * Select instead of asking the admin to type a class path.
     *
     * @return array<string, string>
     */
    public function getDiscoveredModels(): array
    {
        return app(SchemaIntrospector::class)->discoverModels();
    }

    /**
     * Real columns on the currently-selected target_model's table,
     * split into required/optional — powers every column-picking Select
     * on this page (match_target, source_number_column, auto_slug_column,
     * category_target_field) instead of free-text fields the admin has
     * to remember by heart.
     *
     * @return array<string, string> column name => label with a ⚠ marker for required columns
     */
    public function getTargetColumnOptions(): array
    {
        $modelClass = $this->data['target_model'] ?? null;
        if (blank($modelClass)) {
            return [];
        }

        $columns = app(SchemaIntrospector::class)
            ->getTableColumns($modelClass);

        $options = [];
        foreach ($columns as $col) {
            $options[$col['name']] = $col['required']
                ? "{$col['name']}  ⚠ إجباري"
                : $col['name'];
        }

        return $options;
    }

    public function getSourceNumberColumnStatus(): array
    {
        $setting = BridgeSetting::current();
        $columnName = $this->data['source_number_column'] ?? $setting->source_number_column;

        if (blank($setting->target_model) || ! class_exists((string) $setting->target_model)) {
            return ['configured' => false, 'exists' => false, 'table' => null, 'create_sql' => null];
        }

        $modelClass = $setting->target_model;
        $model = new $modelClass;
        $table = $model->getTable();

        if (blank($columnName)) {
            return ['configured' => false, 'exists' => false, 'table' => $table, 'create_sql' => null];
        }

        try {
            $connection = $model->getConnectionName() ?: config('database.default');

            $exists = DB::connection($connection)->selectOne(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?',
                [$table, $columnName]
            ) !== null;
        } catch (\Throwable) {
            return ['configured' => true, 'exists' => false, 'table' => $table, 'create_sql' => null];
        }

        $createSql = "ALTER TABLE `{$table}` ADD COLUMN `{$columnName}` VARCHAR(255) NULL, ADD INDEX (`{$columnName}`);";

        return [
            'configured' => true,
            'exists' => $exists,
            'table' => $table,
            'create_sql' => $exists ? null : $createSql,
        ];
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
            'source_number_column' => $setting->source_number_column,
            'auto_generate_columns' => $setting->auto_generate_columns ?? [],
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
            Wizard::make([
                Step::make('start')
                    ->label('البداية')
                    ->icon('heroicon-o-play')
                    ->description('فعّل الربط واختر موديل المنتج')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('تفعيل الربط التلقائي')
                            ->helperText('عند التفعيل، أي عنصر يتزامن من الأمين/البيان بينحدّث تلقائياً بجدول منتجاتك.'),

                        Select::make('target_model')
                            ->label('موديل المنتج')
                            ->options($this->getDiscoveredModels())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText(empty($this->getDiscoveredModels())
                                ? '⚠ ما قدرنا نلاقي موديلات تلقائياً بمجلد app/Models — تأكد المشروع فيه موديلات فعلاً.'
                                : 'القائمة مبنية تلقائياً من الموديلات الموجودة فعلياً بمشروعك (app/Models).'),
                    ]),

                Step::make('identity')
                    ->label('الهوية الدائمة')
                    ->icon('heroicon-o-finger-print')
                    ->description('أهم خطوة — إجبارية')
                    ->schema([
                        Section::make('هوية دائمة (إجباري) — أهم إعداد بالصفحة كلها')
                            ->description('الباركود ممكن يتغيّر، الاسم ممكن يتعدّل، وحتى لو انمسحت بيانات SqlSync (زي Danger Zone) بيروح أي ربط مبني عليهم. الحل الوحيد المضمون: رقم الصنف الداخلي من برنامج المحاسبة (لا يتكرر أبداً، لا يتغيّر أبداً) يتخزّن مباشرة كعمود على جدول المنتجات نفسه — هيك حتى لو انمسح كل شي تبع SqlSync، أول مزامنة جاية بتلاقي نفس المنتج فوراً بدون أي اعتماد على باركود أو اسم. هاد الإعداد إجباري ولا يمكن تفعيل الربط بدونه.')
                            ->schema([
                                Select::make('source_number_column')
                                    ->label('عمود الهوية الدائمة بجدولك')
                                    ->options(fn () => $this->getTargetColumnOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('لازم يكون عمود موجود فعلياً بجدول المنتجات — النظام بيتحقق تلقائياً ويعطيك أمر الإنشاء الجاهز لو ناقص.')
                                    ->required()
                                    ->live(onBlur: true),
                            ]),
                    ]),

                Step::make('matching')
                    ->label('المطابقة')
                    ->icon('heroicon-o-link')
                    ->description('كيف نعرف الصنف الموجود')
                    ->schema([
                        Section::make('عمود المطابقة')
                            ->description('كيف بنعرف إنو هاد الصنف موجود أصلاً بجدولك؟')
                            ->schema([
                                Select::make('match_source')
                                    ->label('الحقل بالسجل المتزامَن')
                                    ->options($pathOptions)
                                    ->searchable()
                                    ->native(false)
                                    ->helperText($hasData
                                        ? 'اختر الحقل — القيمة يمين اسم الحقل هي عيّنة من آخر سجل مزامَن.'
                                        : '⚠ لا يوجد بيانات مزامنة بعد. اربط الوكيل وقم بأول مزامنة، ثم ارجع لهذه الصفحة لتشوف الحقول المتاحة.')
                                    ->required(),

                                Select::make('match_target')
                                    ->label('العمود بجدولك')
                                    ->options(fn () => $this->getTargetColumnOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText(empty($this->getTargetColumnOptions())
                                        ? 'اختر موديل المنتج بالخطوة الأولى عشان تظهر أعمدته هون.'
                                        : 'الأعمدة المعلّمة ⚠ إجبارية (NOT NULL بدون قيمة افتراضية) بجدولك.')
                                    ->required(),
                            ])
                            ->columns(2),

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
                    ]),

                Step::make('fields')
                    ->label('تعيين الحقول')
                    ->icon('heroicon-o-table-cells')
                    ->description('السعر، الكمية، الاسم...')
                    ->schema([
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
                                    ->itemLabel(function (array $state): ?string {
                                        if (! filled($state['target'] ?? null) || ! filled($state['source'] ?? null)) {
                                            return null;
                                        }

                                        return $state['target'].' ← '.$state['source'];
                                    }),
                            ]),

                        Section::make('توليد Slug تلقائي وآمن (اختياري لكن موصى فيه بشدة)')
                            ->description('لا تربط عمود slug مباشرة بحقل من البيانات المتزامنة (مثل code) — هاد الحقل غالباً فاضي لكتير أصناف أو مش unique، وبيسبب فشل إنشاء كل منتج بهالحالة (Column slug cannot be null / Duplicate entry). بدل هيك، فعّل هالخيار: بيولّد slug تلقائياً من اسم الصنف + معرّف فريد داخلي — مضمون 100% إنه مش فاضي ومش مكرر أبداً.')
                            ->schema([
                                Select::make('auto_slug_column')
                                    ->label('عمود الـ slug بجدولك')
                                    ->options(fn () => $this->getTargetColumnOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('لو حاطط "slug" هون بردو بقسم "تعيين الحقول" فوق، هالإعداد بيفوز دايماً — ما تحتاج تحذفه من هناك يدوياً.'),
                            ]),
                    ]),

                Step::make('category')
                    ->label('التصنيف')
                    ->icon('heroicon-o-tag')
                    ->description('اختياري')
                    ->schema([
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

                                Select::make('category_target_field')
                                    ->label('العمود بجدول المنتجات يلي بياخد معرّف الفئة')
                                    ->options(fn () => $this->getTargetColumnOptions())
                                    ->searchable()
                                    ->native(false),

                                TextInput::make('category_slug_column')
                                    ->label('عمود الـ slug بجدول الفئات (اتركه فاضي إذا مافي)')
                                    ->placeholder('slug'),
                            ])
                            ->columns(2),
                    ]),

                Step::make('defaults')
                    ->label('القيم الافتراضية')
                    ->icon('heroicon-o-check-circle')
                    ->description('آخر خطوة')
                    ->schema([
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
                    ]),
            ])
                ->skippable()
                ->persistStepInQueryString(),
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
    /**
     * The core "smart wizard" behavior: examines the real columns on
     * the selected target model's table, the real fields available
     * from the latest synced record, and pre-fills reasonable guesses
     * across match_target, source_number_column, auto_slug_column,
     * category_target_field, and the fields repeater — using
     * SchemaIntrospector::suggestSource()'s name-similarity + populated-
     * sample-value heuristic.
     *
     * Never silently overwrites values the admin already set — only
     * fills genuinely empty fields, so running this twice (or after
     * manual tweaks) is always safe.
     */
    public function runSmartSuggest(): void
    {
        $modelClass = $this->data['target_model'] ?? null;
        if (blank($modelClass)) {
            Notification::make()
                ->title('اختر موديل المنتج أولاً')
                ->warning()
                ->send();

            return;
        }

        $introspector = app(SchemaIntrospector::class);
        $columns = $introspector->getTableColumns($modelClass);
        $sampleFields = $this->getAvailablePaths();

        if (empty($sampleFields)) {
            Notification::make()
                ->title('لا يوجد بيانات مزامنة بعد')
                ->body('اربط الوكيل وشغّل أول مزامنة قبل ما تقدر تستخدم الاقتراح الذكي.')
                ->warning()
                ->send();

            return;
        }

        $suggestedCount = 0;
        $unmatchedRequired = [];

        // match_target: prefer an obvious barcode/sku-like column
        if (blank($this->data['match_target'] ?? null)) {
            foreach ($columns as $col) {
                $guess = $introspector->suggestSource($col['name'], $sampleFields);
                if ($guess === 'barcode') {
                    $this->data['match_target'] = $col['name'];
                    $this->data['match_source'] = 'barcode';
                    $suggestedCount++;
                    break;
                }
            }
        }

        // fields: for every non-required, non-already-covered column,
        // suggest a mapping if the heuristic is confident enough
        $existingFieldTargets = collect($this->data['fields'] ?? [])->pluck('target')->all();
        $newFieldSuggestions = [];

        foreach ($columns as $col) {
            if ($col['auto_increment']) {
                continue;
            }
            if (in_array($col['name'], $existingFieldTargets, true)) {
                continue;
            }
            if ($col['name'] === ($this->data['match_target'] ?? null)) {
                continue;
            }

            $guess = $introspector->suggestSource($col['name'], $sampleFields);

            if ($guess !== null) {
                $newFieldSuggestions[] = ['target' => $col['name'], 'source' => $guess];
                $suggestedCount++;
            } elseif ($col['required']) {
                $unmatchedRequired[] = $col['name'];
            }
        }

        if (! empty($newFieldSuggestions)) {
            $this->data['fields'] = array_merge($this->data['fields'] ?? [], $newFieldSuggestions);
        }

        // Slug: if there's a 'slug' column with no confident source
        // suggestion, recommend auto-generation rather than a raw
        // (likely unsafe) field mapping — the lesson learned this
        // whole session.
        $slugColumn = collect($columns)->firstWhere('name', 'slug');
        if ($slugColumn && blank($this->data['auto_slug_column'] ?? null)) {
            $this->data['auto_slug_column'] = 'slug';
            $unmatchedRequired = array_diff($unmatchedRequired, ['slug']);
            $suggestedCount++;
        }

        $this->unmatchedRequiredColumns = array_values($unmatchedRequired);

        $body = $suggestedCount > 0
            ? "تم اقتراح {$suggestedCount} إعداد تلقائياً. راجعهم قبل الحفظ."
            : 'ما لقينا اقتراحات جديدة — راجع الأعمدة يدوياً.';

        if (! empty($unmatchedRequired)) {
            $body .= ' ⚠ '.count($unmatchedRequired).' عمود إجباري بدون مصدر واضح — شوف قسم "أعمدة محتاجة قرار" تحت.';
        }

        Notification::make()
            ->title('الاقتراح الذكي')
            ->body($body)
            ->success()
            ->send();
    }

    public ?array $unmatchedRequiredColumns = null;

    /**
     * For a required column with no confident auto-suggestion, the
     * admin picks one of two resolutions per column: a fixed value
     * (goes to create_defaults) or auto-generated uniqueness (goes to
     * auto_generate_columns). Called from the Blade view's per-column
     * action buttons.
     */
    public function resolveUnmatchedColumn(string $column, string $mode, ?string $fixedValue = null): void
    {
        if ($mode === 'fixed') {
            $defaults = $this->data['create_defaults'] ?? [];
            $defaults[$column] = $fixedValue ?? '';
            $this->data['create_defaults'] = $defaults;
        } elseif ($mode === 'auto_generate') {
            $auto = $this->data['auto_generate_columns'] ?? [];
            if (! in_array($column, $auto, true)) {
                $auto[] = $column;
            }
            $this->data['auto_generate_columns'] = $auto;
        }

        $this->unmatchedRequiredColumns = array_values(
            array_diff($this->unmatchedRequiredColumns ?? [], [$column])
        );

        Notification::make()
            ->title("تم تحديد \"{$column}\"")
            ->success()
            ->send();
    }

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

        // Hard requirement, not just a form-level ->required() (which
        // only checks the field isn't empty) — verify the column
        // ACTUALLY exists on the target table before allowing save.
        // This is the strongest identity guarantee the Bridge offers;
        // letting an admin save with a typo'd or non-existent column
        // name would silently degrade back to fragile barcode/name-only
        // matching with no warning.
        $targetModel = $state['target_model'] ?? null;
        $sourceNumberColumn = $state['source_number_column'] ?? null;

        if (blank($sourceNumberColumn)) {
            Notification::make()
                ->title('عمود الهوية الدائمة إجباري')
                ->body('لازم تحدد "عمود الهوية الدائمة بجدولك" قبل ما تقدر تحفظ — هاد أهم إعداد بالصفحة، وبدونه الربط بيرجع يعتمد على باركود/اسم بس، وبيصير عرضة لنفس المشاكل يلي مرقنا فيها.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($targetModel && class_exists($targetModel)) {
            try {
                $model = new $targetModel;
                $table = $model->getTable();
                $connection = $model->getConnectionName() ?: config('database.default');

                $exists = DB::connection($connection)->selectOne(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?',
                    [$table, $sourceNumberColumn]
                ) !== null;

                if (! $exists) {
                    Notification::make()
                        ->title('العمود غير موجود بقاعدة البيانات')
                        ->body("العمود \"{$sourceNumberColumn}\" مش موجود فعلياً بجدول {$table}. شوف الأمر الجاهز فوق الفورم وشغّله أولاً، وبعدها احفظ.")
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }
            } catch (\Throwable) {
                // Can't verify (unsupported driver, permissions) — don't
                // block save over an inability to introspect; the admin
                // is responsible for getting this right in that case.
            }
        }

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
            'source_number_column' => $state['source_number_column'] ?? null,
            'auto_generate_columns' => $state['auto_generate_columns'] ?? [],
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
