<x-filament-panels::page>
    @php
        $status = $this->bridgeStatus ?? [];
        $summaryItems = $this->summaryItems();
        $diagnosticsRows = $this->diagnosticsRows();
        $logs = $this->formattedLogs();
        $state = $this->currentState();
        $showLogsOpen = in_array($state, ['error', 'stopped', 'qr_required'], true);
        $lastError = trim((string) ($status['last_error'] ?? ''));
    @endphp

    <div wire:poll.10s="refreshBridgeData" class="space-y-4" data-whatsapp-session-page>
        <div class="grid gap-4 xl:grid-cols-2">
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 xl:col-span-2"
                data-whatsapp-session-overview
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1.5">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('الحالة الحالية') }}
                        </p>

                        <p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                            {{ $this->currentHint() }}
                        </p>
                    </div>

                    @if (($status['restart_requested'] ?? false) === true)
                        <x-filament::badge color="warning">
                            {{ __('إعادة تشغيل مطلوبة') }}
                        </x-filament::badge>
                    @endif
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($summaryItems as $item)
                        <div class="rounded-xl border border-gray-200/80 bg-gray-50/80 px-3.5 py-3 dark:border-white/10 dark:bg-white/5">
                            <dt class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                {{ $item['label'] }}
                            </dt>
                            <dd class="mt-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $item['value'] }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                @if ($lastError !== '')
                    <div class="mt-4 rounded-xl border border-red-200/70 bg-red-50/80 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-100">
                        <p class="font-medium">{{ __('آخر خطأ') }}</p>
                        <p class="mt-1 break-words leading-6">{{ $lastError }}</p>
                    </div>
                @endif
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5"
                data-whatsapp-session-qr
            >
                <div class="space-y-1">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('QR والربط') }}
                    </h2>

                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                        {{ __('يظهر رمز الربط فقط عندما تحتاج الجلسة إلى مسح QR جديد.') }}
                    </p>
                </div>

                @if ($state === 'qr_required' && $this->canShowQr())
                    <div class="mt-4 overflow-hidden rounded-2xl border border-dashed border-amber-300 bg-amber-50/80 p-4 text-center dark:border-amber-500/30 dark:bg-amber-500/10">
                        <img
                            src="{{ $this->qrDataUrl }}"
                            alt="{{ __('QR واتساب') }}"
                            class="mx-auto h-auto w-full max-w-[260px] rounded-xl bg-white p-2 shadow-sm"
                        >

                        <p class="mt-3 text-xs leading-6 text-amber-800 dark:text-amber-100">
                            {{ __('افتح واتساب من الهاتف ثم الأجهزة المرتبطة وامسح هذا الرمز.') }}
                        </p>
                    </div>
                @elseif ($state === 'qr_required')
                    <div class="mt-4 rounded-2xl border border-dashed border-amber-300 bg-amber-50/70 px-4 py-6 text-center dark:border-amber-500/30 dark:bg-amber-500/10">
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-100">
                            {{ __('الجلسة تحتاج QR لكن الرمز غير متاح بعد') }}
                        </p>
                        <p class="mt-1 text-xs text-amber-800/90 dark:text-amber-100/80">
                            {{ __('استخدم إعادة الربط من الشريط العلوي ثم انتظر بضع ثوانٍ.') }}
                        </p>
                    </div>
                @else
                    <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-white/10 dark:bg-white/5">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('لا يوجد QR مطلوب حاليًا') }}
                        </p>
                    </div>
                @endif
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5"
                data-whatsapp-session-diagnostics
            >
                <div class="space-y-1">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('التشخيص') }}
                    </h2>

                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                        {{ __('ملخص سريع عن العملية والمسارات والبيئة الحالية.') }}
                    </p>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($diagnosticsRows as $row)
                        <div class="rounded-xl border border-gray-200/80 bg-gray-50/80 px-3.5 py-3 dark:border-white/10 dark:bg-white/5">
                            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                {{ $row['label'] }}
                            </p>
                            <p class="mt-1.5 break-all font-mono text-[12px] leading-6 text-gray-900 dark:text-white">
                                {{ $row['value'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 xl:col-span-2"
                data-whatsapp-session-logs
            >
                <details class="group" @if ($showLogsOpen) open @endif>
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="space-y-1">
                                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __('السجلات') }}
                                </h2>

                                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                    {{ __('آخر 60 سطرًا من PM2، مع إبقاء العرض مختصرًا داخل الصفحة.') }}
                                </p>
                            </div>

                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('عرض / إخفاء') }}
                            </span>
                        </div>
                    </summary>

                    @if ($logs !== '')
                        <pre class="mt-4 max-h-72 overflow-auto rounded-xl bg-gray-950 px-4 py-3 text-[11px] leading-6 text-emerald-300 dark:bg-black/60">{{ $logs }}</pre>
                    @else
                        <div class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('لا توجد سجلات متاحة حاليًا.') }}
                        </div>
                    @endif
                </details>
            </section>
        </div>
    </div>
</x-filament-panels::page>
