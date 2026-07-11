<x-filament-panels::page>

    {{-- ── Required columns audit: proactive schema check ────────────
         Scans the target Products table for NOT NULL / no-default
         columns that aren't covered by ANY Bridge configuration (fields,
         create_defaults, match_target, category_target_field). Every one
         of these WILL cause insert to fail for every single new product
         — better to catch that here than discover it one exception at a
         time after running a full sync against thousands of rows. ──── --}}
    @php($missingColumns = $this->getRequiredColumnsAudit())

    @if (!empty($missingColumns))
        <div style="padding: 16px; margin-bottom: 24px; border-radius: 8px;
            background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.35);">
            <p style="margin: 0; font-weight: 600; color: rgb(185, 28, 28);">
                🛑 هاي الأعمدة إجبارية بجدول المنتجات عندك ومش مغطاة بأي إعداد هون:
            </p>
            <p style="margin: 8px 0 0 0; font-family: monospace; direction: ltr; text-align: right; color: rgb(185, 28, 28);">
                {{ implode('  •  ', $missingColumns) }}
            </p>
            <p style="margin: 8px 0 0 0; color: rgb(107, 114, 128); font-size: 13px;">
                لو ما عالجتها، كل محاولة إنشاء منتج جديد رح تفشل بنفس الخطأ (Field 'X' doesn't have a default value).
                ضيفها إما بقسم "تعيين الحقول" (لو القيمة جايّة من البيانات المتزامنة)، أو بقسم "قيم افتراضية عند إنشاء منتج جديد" (لو قيمة ثابتة).
            </p>
        </div>
    @endif

    {{-- ── Diagnostic banner: pinpoints the #1 reason Products stay at 0
         despite records syncing successfully — the exact confusion that
         made this page hard to debug even for the developer himself.
         Shows the FIRST applicable issue, not every possible problem at
         once, and tells you exactly which section below to fix. ────── --}}
    @php($diagnosis = $this->getDiagnosis())

    @if ($diagnosis)
        <div style="padding: 16px; margin-bottom: 24px; border-radius: 8px;
            background: {{ $diagnosis['level'] === 'danger' ? 'rgba(239, 68, 68, 0.08)' : 'rgba(245, 158, 11, 0.08)' }};
            border: 1px solid {{ $diagnosis['level'] === 'danger' ? 'rgba(239, 68, 68, 0.35)' : 'rgba(245, 158, 11, 0.35)' }};">
            <p style="margin: 0; font-weight: 600; color: {{ $diagnosis['level'] === 'danger' ? 'rgb(185, 28, 28)' : 'rgb(180, 83, 9)' }};">
                {{ $diagnosis['level'] === 'danger' ? '🛑' : '⚠️' }} {{ $diagnosis['message'] }}
            </p>
        </div>
    @endif

    {{-- ── Sample data preview: the non-technical user's cheat sheet ────
         Shows the LATEST synced record's fields with their real values.
         The Field Mapping dropdowns below let you pick the same paths
         you see here, so the mental model is:
             "I want price=X on my product. I look here and see
              extra_data.price_4 = 145. That's what I map." ────────────── --}}
    @php($sample = $this->getSampleRecord())
    @php($paths  = $this->getAvailablePaths())

    <x-filament::section
        icon="heroicon-o-eye"
        icon-color="primary"
    >
        <x-slot name="heading">
            بيانات نموذجية من آخر مزامنة
        </x-slot>

        <x-slot name="description">
            @if ($sample)
                هاي بيانات فعلية من آخر سجل زامنه الوكيل ({{ $sample->name ?? '—' }}).
                استخدمها كمرجع لما تربط الحقول تحت — القيم المعروضة هنا هي القيم الفعلية
                يلي رح تتنقل لجدول منتجاتك.
            @else
                لا يوجد بيانات مزامنة بعد.
            @endif
        </x-slot>

        @if ($sample)
            <div style="max-height: 380px; overflow-y: auto; border: 1px solid rgb(229, 231, 235); border-radius: 6px;">
                <table style="width: 100%; border-collapse: collapse; font-family: monospace; font-size: 13px;">
                    <thead style="background: rgba(59, 130, 246, 0.06); position: sticky; top: 0;">
                        <tr>
                            <th style="text-align: right; padding: 8px 12px; border-bottom: 1px solid rgb(229, 231, 235);">اسم الحقل (استخدمه في الربط)</th>
                            <th style="text-align: right; padding: 8px 12px; border-bottom: 1px solid rgb(229, 231, 235);">القيمة الفعلية</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paths as $path => $value)
                            <tr style="border-bottom: 1px solid rgb(243, 244, 246);">
                                <td style="padding: 6px 12px; direction: ltr; text-align: right; color: rgb(37, 99, 235);">{{ $path }}</td>
                                <td style="padding: 6px 12px; color: {{ ($value === null || $value === '') ? 'rgb(156, 163, 175)' : 'inherit' }};">
                                    @if ($value === null || $value === '')
                                        (فاضي)
                                    @elseif (is_bool($value))
                                        {{ $value ? 'true' : 'false' }}
                                    @else
                                        {{ mb_strlen((string) $value) > 60 ? mb_substr((string) $value, 0, 60).'…' : $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p style="margin-top: 12px; color: rgb(107, 114, 128); font-size: 13px;">
                💡 عند اختيار الحقل بالربط تحت، رح تشوف هالقيم بجانب اسم الحقل مباشرة.
            </p>
        @else
            <div style="padding: 24px; background: rgba(251, 191, 36, 0.08); border-radius: 6px; border: 1px solid rgba(251, 191, 36, 0.3);">
                <p style="margin: 0;">
                    ⚠️ ما فيه بيانات لعرضها بعد. الخطوات:
                </p>
                <ol style="margin: 12px 0 0 0; padding-inline-start: 24px;">
                    <li>ركّب برنامج SqlSyncAgent على جهاز الكمبيوتر يلي عنده قاعدة الأمين/البيان.</li>
                    <li>عبّي إعدادات الاتصال واضغط "حفظ".</li>
                    <li>استنى تكمل أول مزامنة (عادةً 30 ثانية أو حسب حجم البيانات).</li>
                    <li>ارجع لهاي الصفحة — رح تشوف كل الحقول المتاحة هون، وبيصير كل شي واضح.</li>
                </ol>
            </div>
        @endif
    </x-filament::section>

    <div style="margin-top: 24px;"></div>

    {{-- ── Quick-fill for the known Al-Bayan pharmacy pattern ──────────
         Removes the part of setup that required reverse-engineering
         Al-Bayan's internal schema (which took real database digging
         even for the developer) — pre-fills everything confirmed from
         that investigation, leaves only project-specific target columns
         for manual entry. ──────────────────────────────────────────── --}}
    <x-filament::button
        type="button"
        color="gray"
        icon="heroicon-o-sparkles"
        wire:click="applyAlBayanPharmacyDefaults"
        wire:confirm="هاد رح يعبّي match_source، fallback matching، وquestion category تلقائياً بالقيم المعروفة من البيان. القيم الحالية بهاي الحقول رح تتبدّل. أكمل؟"
    >
        تعبئة تلقائية — صيدلية بالبيان (Al-Bayan)
    </x-filament::button>

    <div style="margin-top: 12px;"></div>

    {{-- ── Form ───────────────────────────────────────────────────────── --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 24px;">
            <x-filament::button type="submit" icon="heroicon-o-check">
                حفظ الإعدادات
            </x-filament::button>
        </div>
    </form>

    {{-- ── Live mapping preview ───────────────────────────────────────
         For each field mapping the user has set, show what value would
         actually get bridged — so mistakes ("I mapped price to
         extra_data.sel_price but that's null for this customer, I
         should use price_4") are caught BEFORE the user hits save +
         reapply on a 17k-item catalogue. ─────────────────────────── --}}
    @php($preview = $this->getMappingPreview())

    @if (!empty($preview))
        <div style="margin-top: 32px;">
            <x-filament::section icon="heroicon-o-arrow-right-circle" icon-color="success">
                <x-slot name="heading">معاينة الربط الحالي</x-slot>
                <x-slot name="description">
                    هيك رح تنكتب البيانات فوق جدول منتجاتك لو طبّقت الربط الآن على السجل النموذجي فوق.
                    لو شفت "(فاضي)" — يعني الحقل يلي اخترته ما في له قيمة بهاد السجل، جرّب حقل ثاني.
                </x-slot>

                <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px;">
                    <thead style="background: rgba(34, 197, 94, 0.06);">
                        <tr>
                            <th style="text-align: right; padding: 8px 12px; border-bottom: 1px solid rgb(229, 231, 235);">العمود بجدولك</th>
                            <th style="text-align: right; padding: 8px 12px; border-bottom: 1px solid rgb(229, 231, 235);">من الحقل</th>
                            <th style="text-align: right; padding: 8px 12px; border-bottom: 1px solid rgb(229, 231, 235);">القيمة يلي رح تنكتب</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr style="border-bottom: 1px solid rgb(243, 244, 246);">
                                <td style="padding: 8px 12px; font-family: monospace;">{{ $row['target'] }}</td>
                                <td style="padding: 8px 12px; font-family: monospace; color: rgb(37, 99, 235); direction: ltr; text-align: right;">{{ $row['source'] }}</td>
                                <td style="padding: 8px 12px; font-weight: 500; color: {{ $row['ok'] ? 'rgb(21, 128, 61)' : 'rgb(180, 83, 9)' }};">
                                    @if ($row['ok'])
                                        ✓ {{ $row['value'] }}
                                    @else
                                        ⚠ {{ $row['value'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        </div>
    @endif

    {{-- ── Dry run: test on a sample WITHOUT saving anything ───────────
         Runs the exact same bridging logic against the latest 20 synced
         records inside a transaction that always rolls back — real
         constraint checks, zero risk. This is the answer to 'how do we
         stop discovering problems one exception at a time after running
         a full sync on thousands of rows' — test the actual outcome on
         a small sample FIRST. ─────────────────────────────────────── --}}
    <div style="margin-top: 32px;">
        <x-filament::section icon="heroicon-o-beaker" icon-color="info">
            <x-slot name="heading">
                اختبار على عيّنة (بدون أي حفظ فعلي)
            </x-slot>

            <x-slot name="description">
                يجرّب نفس منطق الربط بالضبط على آخر 20 سجل متزامَن — بما فيها محاولة كتابة حقيقية لقاعدة البيانات
                عشان يمسك نفس أخطاء القيود (NOT NULL, UNIQUE) يلي ممكن تصير فعلياً — بس جوا معاملة (transaction) بترجع
                لورا بالكامل. مافي أي سجل بيتغيّر أو ينحفظ. شغّلها كل مرة تعدّل فيها الإعدادات، قبل ما تعمل Reapply
                أو Force Full Resync على آلاف السجلات.
            </x-slot>

            <div style="margin-top: 16px;">
                <x-filament::button
                    type="button"
                    color="info"
                    icon="heroicon-o-beaker"
                    wire:click="runDryRun"
                >
                    اختبار الآن (بدون حفظ)
                </x-filament::button>
            </div>

            @if ($dryRunResults !== null)
                <div style="margin-top: 16px; max-height: 400px; overflow-y: auto; border: 1px solid rgb(229, 231, 235); border-radius: 6px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead style="background: rgba(59, 130, 246, 0.06); position: sticky; top: 0;">
                            <tr>
                                <th style="text-align: right; padding: 8px 12px;">الصنف</th>
                                <th style="text-align: right; padding: 8px 12px;">النتيجة</th>
                                <th style="text-align: right; padding: 8px 12px;">التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dryRunResults as $row)
                                @php($color = match($row['action']) {
                                    'would_create' => 'rgb(21, 128, 61)',
                                    'would_update' => 'rgb(37, 99, 235)',
                                    'error'        => 'rgb(185, 28, 28)',
                                    'skipped'      => 'rgb(180, 83, 9)',
                                    default        => 'rgb(107, 114, 128)',
                                })
                                @php($label = match($row['action']) {
                                    'would_create' => '✓ رح يُنشأ',
                                    'would_update' => '✓ رح يتحدّث',
                                    'error'        => '✖ خطأ',
                                    'skipped'      => '⚠ تخطّي',
                                    default        => $row['action'],
                                })
                                <tr style="border-bottom: 1px solid rgb(243, 244, 246);">
                                    <td style="padding: 6px 12px;">{{ $row['record_name'] }}</td>
                                    <td style="padding: 6px 12px; font-weight: 600; color: {{ $color }};">{{ $label }}</td>
                                    <td style="padding: 6px 12px; font-family: monospace; direction: ltr; text-align: right; color: rgb(107, 114, 128); font-size: 12px;">
                                        {{ $row['detail'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @php($errorCount = collect($dryRunResults)->where('action', 'error')->count())
                @if ($errorCount > 0)
                    <p style="margin-top: 12px; color: rgb(185, 28, 28); font-weight: 600;">
                        ⚠ {{ $errorCount }} من {{ count($dryRunResults) }} فشلوا بالاختبار — عالجهم قبل ما تشغّل مزامنة كاملة.
                    </p>
                @else
                    <p style="margin-top: 12px; color: rgb(21, 128, 61); font-weight: 600;">
                        ✓ كل العيّنة نجحت — آمن تشغّل Reapply أو Force Full Resync الآن.
                    </p>
                @endif
            @endif
        </x-filament::section>
    </div>

    {{-- ── Reapply-to-existing (unchanged behavior) ─────────────────── --}}
    <div style="margin-top: 32px;">
        <x-filament::section>
            <x-slot name="heading">
                إعادة تطبيق الربط على البيانات الموجودة
            </x-slot>

            <x-slot name="description">
                لو غيّرت الإعدادات فوق بعد ما صار عندك Sync سابق، اضغط هون لإعادة تشغيل الربط على كل سجل موجود أصلاً — بدون الحاجة لمزامنة جديدة من الويندوز. العملية بتشتغل بالخلفية.
            </x-slot>

            <div style="margin-top: 16px;">
                <x-filament::button
                    type="button"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:click="reapplyToExisting"
                    wire:confirm="هيك بيبلّش معالجة كل سجل موجود أصلاً بـ Synced Records بالخلفية. أكمل؟"
                >
                    إعادة التطبيق الآن
                </x-filament::button>
            </div>

            @php($progress = $this->getReapplyProgress())

            @if ($progress && $progress['status'] === 'running')
                @php($handled = $progress['done'] + $progress['failed'])
                @php($pct = $progress['total'] > 0 ? intval($handled / $progress['total'] * 100) : 0)

                <div wire:poll.2s style="margin-top: 16px;">
                    <p style="margin: 0;">⏳ جاري المعالجة بالخلفية: {{ $handled }} / {{ $progress['total'] }} ({{ $pct }}%)</p>
                </div>
            @elseif ($progress)
                <div style="margin-top: 16px;">
                    <p style="margin: 0;">
                        ✅ آخر معالجة: {{ $progress['done'] }} نجح
                        @if ($progress['failed'] > 0)
                            ، {{ $progress['failed'] }} اتجاهل
                        @endif
                        — من أصل {{ $progress['total'] }}.
                    </p>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
