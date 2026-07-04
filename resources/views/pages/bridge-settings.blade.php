<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">
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
        <div wire:poll.2s="{{ $progress['status'] === 'running' ? '$refresh' : '' }}" class="mt-6">
            <x-filament::section>
                @if ($progress['status'] === 'running')
                    @php($handled = $progress['done'] + $progress['failed'])
                    @php($pct = $progress['total'] > 0 ? intval($handled / $progress['total'] * 100) : 0)

                    <div class="flex items-center gap-3">
                        <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
                        <span class="font-medium">جاري المعالجة بالخلفية…</span>
                        <x-filament::badge color="gray">{{ $handled }} / {{ $progress['total'] }}</x-filament::badge>
                    </div>

                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-primary-600 transition-all duration-500" style="width: {{ $pct }}%"></div>
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        تقدر تسكّر الصفحة وترجعلها لاحقاً — العملية مستمرة بالخلفية وما بتتوقف.
                    </p>
                @else
                    <div class="flex items-center gap-3">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-success-600" />
                        <span class="font-medium">اكتملت آخر معالجة</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-filament::badge color="success">{{ $progress['done'] }} نجح</x-filament::badge>
                        @if ($progress['failed'] > 0)
                            <x-filament::badge color="warning">{{ $progress['failed'] }} اتجاهل</x-filament::badge>
                        @endif
                        <x-filament::badge color="gray">من أصل {{ $progress['total'] }}</x-filament::badge>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
