<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit">
                حفظ الإعدادات
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-arrow-path"
                wire:click="reapplyToExisting"
                wire:confirm="هيك بيبلّش معالجة كل سجل موجود أصلاً بـ Synced Records بالخلفية (بدون ما تنتظر). أكمل؟"
            >
                إعادة تطبيق الربط على كل السجلات
            </x-filament::button>
        </div>
    </form>

    @php($progress = $this->getReapplyProgress())
    @if ($progress)
        <div
            @if($progress['status'] === 'running') wire:poll.2s @endif
            class="mt-6 rounded-xl border p-4"
            style="border-color: var(--gray-200, #e5e7eb);"
        >
            @if ($progress['status'] === 'running')
                @php($pct = $progress['total'] > 0 ? intval(($progress['done'] + $progress['failed']) / $progress['total'] * 100) : 0)
                <div class="flex items-center justify-between mb-2 text-sm">
                    <span>جاري المعالجة بالخلفية… {{ $progress['done'] + $progress['failed'] }} / {{ $progress['total'] }}</span>
                    <span>{{ $pct }}%</span>
                </div>
                <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full rounded-full bg-primary-600" style="width: {{ $pct }}%"></div>
                </div>
            @else
                <div class="text-sm">
                    ✅ اكتملت آخر معالجة: {{ $progress['done'] }} نجح، {{ $progress['failed'] }} اتجاهل، من أصل {{ $progress['total'] }}.
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>

