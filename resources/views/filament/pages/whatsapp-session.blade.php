<x-filament-panels::page>
    @php
        $status = $this->bridgeStatus ?? [];
        $summaryItems = $this->summaryItems();
        $diagnosticsRows = $this->diagnosticsRows();
        $logs = $this->formattedLogs();
    @endphp

    <div wire:poll.10s="refreshBridgeData" class="space-y-6">
        <x-filament::section compact :heading="__('جلسة واتساب')" :description="__('مراقبة حالة البريدج والجلسة بشكل حي كل 10 ثوانٍ.')">
            <x-slot name="afterHeader">
                <x-filament::badge :color="$this->currentBadge()">
                    {{ $this->currentLabel() }}
                </x-filament::badge>
            </x-slot>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('الحالة الحالية') }}</p>
                                <h3 class="text-xl font-semibold text-gray-950 dark:text-white">{{ $this->currentLabel() }}</h3>
                            </div>

                            <div class="flex max-w-full flex-col items-start gap-3 sm:items-end">
                                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <x-filament::badge :color="$this->currentBadge()">
                                        {{ strtoupper((string) ($status['pm2_status'] ?? 'unknown')) }}
                                    </x-filament::badge>

                                    @if (($status['restart_requested'] ?? false) === true)
                                        <x-filament::badge color="warning">
                                            {{ __('إعادة تشغيل مطلوبة') }}
                                        </x-filament::badge>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <x-filament::button
                                        color="warning"
                                        icon="heroicon-o-arrow-path"
                                        outlined
                                        size="sm"
                                        type="button"
                                        wire:click="mountAction('restartBridge')"
                                    >
                                        {{ __('إعادة تشغيل البريدج') }}
                                    </x-filament::button>

                                    <x-filament::button
                                        color="info"
                                        icon="heroicon-o-qr-code"
                                        outlined
                                        size="sm"
                                        type="button"
                                        wire:click="mountAction('reconnectQr')"
                                    >
                                        {{ __('إعادة الربط / Reconnect') }}
                                    </x-filament::button>

                                    @if ($this->canShowQr())
                                        <x-filament::button
                                            color="gray"
                                            icon="heroicon-o-eye"
                                            outlined
                                            size="sm"
                                            tag="a"
                                            href="#whatsapp-session-qr"
                                        >
                                            {{ __('عرض QR') }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                            {{ $this->currentHint() }}
                        </p>

                        <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($summaryItems as $item)
                                <div class="rounded-xl border border-gray-200/80 bg-gray-50/70 px-3 py-3 dark:border-white/10 dark:bg-white/5">
                                    <dt class="text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                                    <dd class="mt-1.5 text-sm font-semibold text-gray-950 dark:text-white">{{ $item['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div id="whatsapp-session-qr" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('تشخيص العملية') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ملخص سريع لـ PM2 وملفات الجلسة بدون الدخول إلى السجلات الكاملة.') }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            @foreach ($diagnosticsRows as $row)
                                <div class="rounded-xl border border-gray-200/80 bg-gray-50/70 px-3 py-3 dark:border-white/10 dark:bg-white/5">
                                    <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $row['label'] }}</p>
                                    <p class="mt-1.5 break-all font-mono text-[12px] text-gray-900 dark:text-white">{{ $row['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('QR والربط') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('يظهر QR فقط عندما تحتاج الجلسة إلى إعادة ربط فعلية.') }}</p>
                            </div>

                            <x-filament::badge :color="$this->currentBadge()">
                                {{ $this->currentLabel() }}
                            </x-filament::badge>
                        </div>

                        @if ($this->canShowQr())
                            <div class="mt-4 overflow-hidden rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-4 text-center dark:border-amber-500/30 dark:bg-amber-500/10">
                                <img
                                    src="{{ $this->qrDataUrl }}"
                                    alt="{{ __('QR واتساب') }}"
                                    class="mx-auto h-auto w-full max-w-[280px] rounded-xl bg-white p-2 shadow-sm"
                                >
                                <p class="mt-3 text-xs leading-6 text-amber-800 dark:text-amber-100">
                                    {{ __('افتح واتساب في الهاتف ثم الأجهزة المرتبطة ثم اربط الجهاز عبر هذا الرمز.') }}
                                </p>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-white/10 dark:bg-white/5">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('لا يوجد QR معروض حاليًا') }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('إذا كانت الحالة تحتاج QR فاستعمل زر إعادة الربط من الأعلى وانتظر بضع ثوانٍ.') }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('آخر السجلات') }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('آخر 60 سطرًا من PM2 لتشخيص الأعطال أو تأخر الربط.') }}</p>
                            </div>
                        </div>

                        @if ($logs !== '')
                            <pre class="mt-4 max-h-[420px] overflow-auto rounded-2xl bg-[#0b1220] p-3 text-[11px] leading-6 text-emerald-300">{{ $logs }}</pre>
                        @else
                            <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                {{ __('لا توجد سجلات متاحة حاليًا.') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
