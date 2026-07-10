<x-filament-panels::page>

    <div style="padding: 16px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 8px; margin-bottom: 24px;">
        <p style="margin: 0; font-weight: 600; color: rgb(185, 28, 28);">
            ⚠️ هاي منطقة خطرة. العمليات هون نهائية ومالها Undo.
        </p>
        <p style="margin: 8px 0 0 0; color: rgb(107, 114, 128); font-size: 13px;">
            استخدمها بس للتجربة، أو لما تعيد بناء قاعدة بيانات عميل من الصفر.
            ما تستخدمها على نظام شغّال فيه بيانات حقيقية إلا وانت متأكد 100%.
        </p>
    </div>

    <form wire:submit.prevent="performReset">
        {{ $this->form }}

        <div style="margin-top: 24px;">
            @php($confirmed = ($this->data['confirm_phrase'] ?? '') === 'RESET')

            <x-filament::button
                type="submit"
                color="danger"
                icon="heroicon-o-trash"
                :disabled="!$confirmed"
                wire:confirm="متأكد 100%؟ هاد الإجراء نهائي ومالوش رجعة."
            >
                نفّذ إعادة التعيين الآن
            </x-filament::button>

            @unless ($confirmed)
                <p style="margin-top: 8px; color: rgb(156, 163, 175); font-size: 13px;">
                    الزر معطّل لحد ما تكتب RESET بالحقل فوق بالضبط.
                </p>
            @endunless
        </div>
    </form>

</x-filament-panels::page>
