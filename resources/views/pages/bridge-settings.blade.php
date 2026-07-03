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
                wire:confirm="هيك بيعيد تشغيل الربط على كل سجل موجود أصلاً بـ Synced Records، حتى لو ما تغيّر شي بقاعدة بيانات المحاسبة. أكمل؟"
                wire:loading.attr="disabled"
                wire:target="reapplyToExisting"
            >
                إعادة تطبيق الربط على كل السجلات
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
