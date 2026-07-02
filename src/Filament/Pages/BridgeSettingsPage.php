<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use SqlSync\FilamentSqlSync\SqlSyncFilamentPlugin;
use SqlSync\LaravelSqlSync\Models\BridgeSetting;

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
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('التفعيل والموديل الهدف')
                ->description('حدد موديل المنتج الخاص بمشروعك — كل مشروع بيقدر يكون مختلف كلياً.')
                ->schema([
                    Toggle::make('enabled')
                        ->label('تفعيل الربط التلقائي')
                        ->helperText('عند التفعيل، أي عنصر يتزامن من الامين/البيان بينحدّث تلقائياً بجدول منتجاتك.'),

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
                    TextInput::make('match_source')
                        ->label('الحقل بالسجل المتزامَن')
                        ->placeholder('barcode')
                        ->helperText('يدعم extra_data.اسم_الحقل لأسعار/وحدات الامين، مثل extra_data.mtRetail')
                        ->required(),

                    TextInput::make('match_target')
                        ->label('العمود بجدولك')
                        ->placeholder('sku')
                        ->required(),
                ])
                ->columns(2),

            Section::make('تعيين الحقول (Field Mapping)')
                ->description('أي أعمدة بجدولك بدك تنحدّث تلقائياً، ومن وين تجيب قيمتها.')
                ->schema([
                    Repeater::make('fields')
                        ->label('')
                        ->schema([
                            TextInput::make('target')
                                ->label('العمود بجدولك')
                                ->placeholder('price')
                                ->required(),
                            TextInput::make('source')
                                ->label('الحقل بالسجل المتزامَن')
                                ->placeholder('extra_data.mtRetail')
                                ->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('إضافة حقل')
                        ->reorderable(false),
                ]),

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

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('حفظ الإعدادات')
                ->submit('save'),
        ];
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
        ]);

        Notification::make()
            ->title('تم حفظ إعدادات الربط')
            ->success()
            ->send();
    }
}
