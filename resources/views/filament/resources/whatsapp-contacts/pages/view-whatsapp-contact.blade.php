<x-filament-panels::page>
    @php
        $contact = $this->getContact();
        $status = $this->statusMeta();
        $latestMessage = $contact->latestMessage;
        $summaryItems = [
            ['label' => 'رقم الهاتف', 'value' => $contact->phone ?: '—', 'ltr' => true],
            ['label' => 'القسم / الوحدة', 'value' => $contact->department?->hierarchy_name ?? '—', 'ltr' => false],
            ['label' => 'الموقع الافتراضي', 'value' => $contact->default_location ?: '—', 'ltr' => false],
            ['label' => 'الموظف المرتبط', 'value' => $contact->user?->name ?? 'غير مرتبط', 'ltr' => false],
            ['label' => 'آخر رسالة', 'value' => $contact->last_message_at?->format('Y-m-d H:i') ?? '—', 'ltr' => false],
            ['label' => 'الحالة', 'value' => $status['label'], 'ltr' => false],
        ];
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_340px]">
        <div class="space-y-6">
            <x-filament::section compact :heading="$contact->displayName()" :description="__('ملخص جهة الاتصال واستخدامها في محادثات واتساب والتحويل إلى مهام.')">
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$status['color']">
                        {{ $status['label'] }}
                    </x-filament::badge>
                </x-slot>

                <div class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($summaryItems as $item)
                            <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                                <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                                <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white" @if ($item['ltr']) dir="ltr" style="unicode-bidi:isolate" @endif>
                                    {{ $item['value'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <a
                        href="{{ $this->conversationUrl() }}"
                        class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-400"
                    >
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-4 w-4" />
                        <span>{{ __('فتح المحادثة') }}</span>
                    </a>
                </div>
            </x-filament::section>

            <x-filament::section compact :heading="__('آخر رسالة مرتبطة')" :description="__('آخر محتوى وصل أو أرسل عبر هذا الرقم.')">
                @if ($latestMessage)
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="$latestMessage->isIncoming() ? 'info' : 'success'">
                                {{ $latestMessage->isIncoming() ? __('واردة') : __('صادرة') }}
                            </x-filament::badge>

                            @if ($latestMessage->task)
                                <x-filament::badge color="warning">
                                    {{ __('مرتبطة بالمهمة') }} {{ $latestMessage->task->displayNumber() }}
                                </x-filament::badge>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                            {{ $contact->latestMessagePreview() }}
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                        {{ __('لا توجد رسائل مسجلة لهذه الجهة حتى الآن.') }}
                    </div>
                @endif
            </x-filament::section>
        </div>

        <div class="space-y-6">
            <x-filament::section compact :heading="__('ملاحظات سريعة')" :description="__('البيانات الافتراضية تستخدم عند إنشاء المهام من واتساب أو توجيهها داخليًا.')">
                <ul class="space-y-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    <li>{{ __('الاسم ورقم الهاتف يظهران في شاشة المحادثة والربط الداخلي.') }}</li>
                    <li>{{ __('القسم والموقع يساعدان في تعبئة المهمة بشكل أسرع عند التحويل.') }}</li>
                    <li>{{ __('يمكن تعديل الربط أو فتح المحادثة مباشرة من أزرار الأعلى.') }}</li>
                </ul>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
