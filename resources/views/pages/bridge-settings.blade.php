<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end border-t border-gray-200 pt-6 dark:border-white/10">
            <x-filament::button type="submit" icon="heroicon-o-check">
                حفظ الإعدادات
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6">
        <x-filament::section icon="heroicon-o-arrow-path" icon-color="gray">
            <x-slot name="heading">
                إعادة تطبيق الربط على البيانات الموجودة
            </x-slot>
            <x-slot name="description">
                لو غيّرت الإعدادات فوق بعد ما صار عندك Sync سابق، اضغط هون لإعادة تشغيل الربط على كل سجل موجود أصلاً — بدون الحاجة لمزامنة جديدة من الويندوز. العملية بتشتغل بالخلفية وما بتوقّفك.
            </x-slot>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    @php($progress = $this->getReapplyProgress())
                    @if ($progress)
                        <div wire:poll.2s="{{ $progress['status'] === 'running' ? '$refresh' : '' }}">
                            @if ($progress['status'] === 'running')
                                @php($handled = $progress['done'] + $progress['failed'])
                                @php($pct = $progress['total'] > 0 ? intval($handled / $progress['total'] * 100) : 0)

                                <div class="flex items-center gap-3">
                                    <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
                                    <span class="text-sm font-medium">جاري المعالجة بالخلفية…</span>
                                    <x-filament::badge color="gray">{{ $handled }} / {{ $progress['total'] }}</x-filament::badge>
                                </div>
                                <div class="mt-3 h-2 w-full max-w-md overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-primary-600 transition-all duration-500" style="width: {{ $pct }}%"></div>
                                </div>
                            @else
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-600" />
                                    <span class="text-sm font-medium">اكتملت آخر معالجة:</span>
                                    <x-filament::badge color="success">{{ $progress['done'] }} نجح</x-filament::badge>
                                    @if ($progress['failed'] > 0)
                                        <x-filament::badge color="warning">{{ $progress['failed'] }} اتجاهل</x-filament::badge>
                                    @endif
                                    <x-filament::badge color="gray">من أصل {{ $progress['total'] }}</x-filament::badge>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">لسا ما تمت أي معالجة يدوية لهاد المشروع.</p>
                    @endif
                </div>

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
        </x-filament::section>
    </div>
</x-filament-panels::page>
