<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" icon="heroicon-o-check">
                حفظ الإعدادات
            </x-filament::button>
        </div>
    </form>

    <x-filament::section>
        <x-slot name="heading">
            إعادة تطبيق الربط على البيانات الموجودة
        </x-slot>

        <x-slot name="description">
            لو غيّرت الإعدادات فوق بعد ما صار عندك Sync سابق، اضغط هون لإعادة تشغيل الربط على كل سجل موجود أصلاً — بدون الحاجة لمزامنة جديدة من الويندوز. العملية بتشتغل بالخلفية.
        </x-slot>

        @php($progress = $this->getReapplyProgress())

        @if ($progress && $progress['status'] === 'running')
            @php($handled = $progress['done'] + $progress['failed'])
            @php($pct = $progress['total'] > 0 ? intval($handled / $progress['total'] * 100) : 0)

            <div wire:poll.2s>
                <div class="fi-badge">جاري المعالجة: {{ $handled }} / {{ $progress['total'] }} ({{ $pct }}%)</div>
            </div>
        @elseif ($progress)
            <p>
                ✅ آخر معالجة: {{ $progress['done'] }} نجح
                @if ($progress['failed'] > 0)
                    ، {{ $progress['failed'] }} اتجاهل
                @endif
                — من أصل {{ $progress['total'] }}.
            </p>
        @endif

        <x-slot name="headerEnd">
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-arrow-path"
                wire:click="reapplyToExisting"
                wire:confirm="هيك بيبلّش معالجة كل سجل موجود أصلاً بـ Synced Records بالخلفية. أكمل؟"
            >
                إعادة التطبيق الآن
            </x-filament::button>
        </x-slot>
    </x-filament::section>
</x-filament-panels::page>
