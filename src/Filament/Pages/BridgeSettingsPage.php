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
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Jobs\ReapplyBridgeJob;
use SqlSync\LaravelSqlSync\Models\BridgeSetting;
use SqlSync\LaravelSqlSync\Models\SyncedRecord;

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

    public function mount(): void
    {
        $setting = BridgeSetting::current();

        $this->form->fill([
            'enabled' => $setting->enabled,
            'target_model' => $setting->target_model,
            'match_source' => $setting->match_source,
            'match_target' => $setting->match_target,
            'fields' => collect($setting->fields ?? [])
                ->map(fn ($source, $target) => ['target' => $target, 'source' => $source])
                ->values()
                ->all(),
            'create_defaults' => $setting->create_defaults ?? [],
            'skip_create_if_missing_defaults' => $setting->skip_create_if_missing_defaults,
            'category_model' => $setting->category_model,
            'category_source' => $setting->category_source,
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
        $hasData     = ! empty($pathOptions);

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
                        ->itemLabel(fn (array $state): ?string => (filled($state['target'] ?? null) && filled($state['source'] ?? null))
                            ? ($state['target'].' ← '.$state['source'])
                            : null
                        ),
                ]),

            Section::make('التصنيف التلقائي (اختياري)')
                ->description('لو صنف جديد جاي بفئة (مثل "أدوات وصيانة") مش موجودة بجدول الفئات عندك، بتنخلق تلقائياً وبتترابط بالمنتج — بدل ما تعلّق عملية الإنشاء بسبب حقل category_id الإجباري.')
                ->schema([
                    TextInput::make('category_model')
                        ->label('اسم الـ Model بالكامل')
                        ->placeholder('App\\Models\\Category'),

                    Select::make('category_source')
                        ->label('الحقل بالسجل المتزامَن')
                        ->options($pathOptions)
                        ->searchable()
                        ->native(false)
                        ->placeholder('اختر الحقل يلي بيحدد اسم الفئة'),

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
                'value'  => $this->formatSampleValue($value) ?? '(فاضي)',
                'ok'     => $value !== null && $value !== '',
            ];
        }

        return $preview;
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $fields = collect($state['fields'] ?? [])
            ->filter(fn ($row) => filled($row['target'] ?? null))
            ->mapWithKeys(fn ($row) => [$row['target'] => $row['source']])
            ->all();

        $setting = BridgeSetting::current();
        $setting->update([
            'enabled' => $state['enabled'] ?? false,
            'target_model' => $state['target_model'] ?? null,
            'match_source' => $state['match_source'] ?? null,
            'match_target' => $state['match_target'] ?? null,
            'fields' => $fields,
            'create_defaults' => $state['create_defaults'] ?? [],
            'skip_create_if_missing_defaults' => $state['skip_create_if_missing_defaults'] ?? true,
            'category_model' => $state['category_model'] ?? null,
            'category_source' => $state['category_source'] ?? null,
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
