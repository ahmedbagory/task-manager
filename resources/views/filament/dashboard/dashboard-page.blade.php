<x-filament-panels::page class="tm-dashboard-page">
    @once
        <style>
            .tm-dashboard-page [data-dashboard-clock] .tm-clock-hand {
                position: absolute;
                left: 50%;
                bottom: 50%;
                transform-origin: center bottom;
                border-radius: 999px;
            }
        </style>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('tmDashboardClock', (locale) => ({
                    now: new Date(),
                    timer: null,
                    locale,
                    init() {
                        this.tick();
                        this.timer = window.setInterval(() => this.tick(), 1000);
                    },
                    destroy() {
                        if (this.timer) {
                            window.clearInterval(this.timer);
                        }
                    },
                    tick() {
                        this.now = new Date();
                    },
                    get hourRotation() {
                        return ((this.now.getHours() % 12) * 30) + (this.now.getMinutes() * 0.5);
                    },
                    get minuteRotation() {
                        return (this.now.getMinutes() * 6) + (this.now.getSeconds() * 0.1);
                    },
                    get secondRotation() {
                        return this.now.getSeconds() * 6;
                    },
                    get dateLabel() {
                        return new Intl.DateTimeFormat(this.locale, {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        }).format(this.now);
                    },
                    get timeLabel() {
                        return new Intl.DateTimeFormat(this.locale, {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false,
                        }).format(this.now);
                    },
                }));
            });
        </script>
    @endonce

    @php
        $isManagement = ($dashboard['role'] ?? 'employee') === 'management';
        $toneClasses = static fn (string $tone): string => match ($tone) {
            'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
            'danger' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20',
            'warning' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
            'info', 'primary' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20',
            default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10',
        };
    @endphp

    <div wire:poll.30s="refreshDashboardState" data-dashboard-role="{{ $dashboard['role'] ?? 'employee' }}" class="mx-auto flex w-full max-w-7xl flex-col gap-4">
        <section class="rounded-3xl border border-gray-200 bg-white px-5 py-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="space-y-3">
                    <div class="space-y-1">
                        <p class="text-xs font-medium tracking-[0.2em] text-gray-400">{{ $isManagement ? __('مركز التشغيل') : __('تشغيلي اليوم') }}</p>
                        <h1 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $dashboard['headline'] ?? __('لوحة التحكم') }}</h1>
                        <p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $dashboard['summary'] ?? '' }}</p>
                    </div>

                    @if (! empty($dashboard['top_pills']))
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach ($dashboard['top_pills'] as $pill)
                                <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                    <span class="text-gray-500 dark:text-gray-400">{{ $pill['label'] }}</span>
                                    <span>{{ $pill['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div
                    data-dashboard-clock
                    x-data="tmDashboardClock(@js($clockLocale))"
                    class="flex items-center gap-4 self-start rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-gray-950/70"
                >
                    <div class="relative h-24 w-24 rounded-full border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                        @for ($tick = 0; $tick < 12; $tick++)
                            <span class="absolute inset-2.5" style="transform: rotate({{ $tick * 30 }}deg);">
                                <span class="mx-auto block rounded-full bg-gray-300 dark:bg-gray-600 {{ $tick % 3 === 0 ? 'h-3 w-0.5' : 'h-2 w-px' }}"></span>
                            </span>
                        @endfor

                        <span class="tm-clock-hand h-6 w-1.5 bg-gray-900 dark:bg-white" :style="`transform: translateX(-50%) rotate(${hourRotation}deg)`"></span>
                        <span class="tm-clock-hand h-8 w-1 bg-gray-500 dark:bg-gray-300" :style="`transform: translateX(-50%) rotate(${minuteRotation}deg)`"></span>
                        <span class="tm-clock-hand h-9 w-0.5 bg-amber-500" :style="`transform: translateX(-50%) rotate(${secondRotation}deg)`"></span>
                        <span class="absolute left-1/2 top-1/2 h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gray-900 dark:bg-white"></span>
                    </div>

                    <div class="space-y-1 text-start">
                        <p data-dashboard-date class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="dateLabel"></p>
                        <p data-dashboard-time class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white tabular-nums" x-text="timeLabel"></p>
                    </div>
                </div>
            </div>
        </section>

        @if ($isManagement)
            <section data-dashboard-management-kpis class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($dashboard['kpis'] ?? [] as $kpi)
                    <article class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                                <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white tabular-nums">{{ $kpi['value'] }}</p>
                                @if (! empty($kpi['meta']))
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['meta'] }}</p>
                                @endif
                            </div>

                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses($kpi['tone'] ?? 'gray') }}">
                                {{ $kpi['label'] }}
                            </span>
                        </div>

                        @if (! empty($kpi['url']))
                            <div class="mt-3">
                                <x-filament::button tag="a" href="{{ $kpi['url'] }}" color="gray" size="sm" outlined>
                                    {{ __('فتح') }}
                                </x-filament::button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_minmax(0,0.95fr)]">
                <article class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('يحتاج قرار الآن') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ملفات تشغيلية تحتاج متابعة مباشرة.') }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach ($dashboard['needs_action'] ?? [] as $item)
                            <div class="rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 dark:border-white/10 dark:bg-white/5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('حاليًا: :count', ['count' => number_format($item['count'])]) }}</p>
                                    </div>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses($item['tone'] ?? 'gray') }}">
                                        {{ number_format($item['count']) }}
                                    </span>
                                </div>

                                @if (! empty($item['actions']))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($item['actions'] as $action)
                                            <x-filament::button tag="a" href="{{ $action['url'] }}" color="gray" size="sm" outlined>
                                                {{ $action['label'] }}
                                            </x-filament::button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('توزيع العمل على الموظفين') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('الأعلى من حيث المهام المفتوحة والمتأخرة وقيد التنفيذ.') }}</p>
                        </div>
                    </div>

                    @if (! empty($dashboard['team_workload']))
                        <div class="mt-4 space-y-3">
                            @foreach ($dashboard['team_workload'] as $member)
                                <a href="{{ $member['url'] }}" class="block rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 transition hover:border-gray-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $member['name'] }}</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('المفتوحة: :count', ['count' => number_format($member['open_tasks'])]) }}</span>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                        <span class="inline-flex rounded-full px-2.5 py-1 ring-1 ring-inset {{ $toneClasses('gray') }}">{{ __('مفتوحة :count', ['count' => number_format($member['open_tasks'])]) }}</span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 ring-1 ring-inset {{ $toneClasses('danger') }}">{{ __('متأخرة :count', ['count' => number_format($member['overdue_tasks'])]) }}</span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 ring-1 ring-inset {{ $toneClasses('primary') }}">{{ __('قيد التنفيذ :count', ['count' => number_format($member['in_progress_tasks'])]) }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('لا توجد بيانات توزيع كافية حاليًا.') }}
                        </div>
                    @endif
                </article>

                <article class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('الأقسام الأكثر طلبًا') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('أكثر الجهات التي عليها ضغط مهام الآن.') }}</p>
                        </div>
                    </div>

                    @if (! empty($dashboard['departments']))
                        <div class="mt-4 space-y-2.5">
                            @foreach ($dashboard['departments'] as $department)
                                <a href="{{ $department['url'] }}" class="flex items-center justify-between rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 transition hover:border-gray-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $department['label'] }}</span>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $toneClasses('gray') }}">{{ number_format($department['count']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('لا توجد بيانات أقسام حالية.') }}
                        </div>
                    @endif
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
                <article class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('عمليات واتساب') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $dashboard['whatsapp']['status_hint'] ?? '' }}</p>
                        </div>

                        <x-filament::badge :color="$dashboard['whatsapp']['status_tone'] ?? 'gray'">
                            {{ $dashboard['whatsapp']['status_label'] ?? __('غير متصل') }}
                        </x-filament::badge>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('رسائل واتساب المحولة لمهام') }}</p>
                            <p class="mt-2 text-xl font-semibold text-gray-950 dark:text-white tabular-nums">{{ number_format($dashboard['whatsapp']['converted_tasks'] ?? 0) }}</p>
                        </div>

                        <div class="rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('رسائل غير معالجة') }}</p>
                            <p class="mt-2 text-xl font-semibold text-gray-950 dark:text-white tabular-nums">{{ number_format($dashboard['whatsapp']['unprocessed_messages'] ?? 0) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('آخر رسالة') }}</p>
                        @if (! empty($dashboard['whatsapp']['last_message']))
                            <a href="{{ $dashboard['whatsapp']['last_message']['url'] }}" class="mt-2 block">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $dashboard['whatsapp']['last_message']['summary'] }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $dashboard['whatsapp']['last_message']['meta'] }}</p>
                            </a>
                        @else
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('لا توجد رسائل حديثة.') }}</p>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button tag="a" href="{{ $dashboard['whatsapp']['open_inbox_url'] }}" color="gray" size="sm" outlined>
                            {{ __('فتح واتساب') }}
                        </x-filament::button>

                        @if ($dashboard['whatsapp']['can_manage_session'] ?? false)
                            <x-filament::button tag="a" href="{{ $dashboard['whatsapp']['session_url'] }}" color="gray" size="sm" outlined>
                                {{ __('فتح الجلسة') }}
                            </x-filament::button>

                            <x-filament::button color="warning" icon="heroicon-o-arrow-path" size="sm" type="button" wire:click="mountAction('restartBridge')">
                                {{ __('إعادة تشغيل البريدج') }}
                            </x-filament::button>

                            <x-filament::button color="info" icon="heroicon-o-qr-code" size="sm" type="button" wire:click="mountAction('reconnectBridge')">
                                {{ __('إعادة الربط') }}
                            </x-filament::button>

                            @if ($dashboard['whatsapp']['show_qr_button'] ?? false)
                                <x-filament::button tag="a" href="{{ $dashboard['whatsapp']['qr_url'] }}" color="gray" size="sm" outlined>
                                    {{ __('عرض QR') }}
                                </x-filament::button>
                            @endif
                        @endif
                    </div>
                </article>

                <article class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('آخر النشاط') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('آخر 5 عمليات مؤثرة على التشغيل.') }}</p>
                        </div>
                    </div>

                    @if (! empty($dashboard['recent_activity']))
                        <div class="mt-4 space-y-3">
                            @foreach ($dashboard['recent_activity'] as $activity)
                                <a href="{{ $activity['url'] }}" class="block rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 transition hover:border-gray-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $activity['title'] }}</p>
                                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $activity['description'] }}</p>
                                            @if (! empty($activity['meta']))
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $activity['meta'] }}</p>
                                            @endif
                                        </div>

                                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $activity['at_label'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('لا توجد حركة حديثة بعد.') }}
                        </div>
                    @endif
                </article>
            </section>

            @if (! empty($dashboard['my_tasks']))
                <section class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('مهامي أنا') }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ملخص شخصي سريع أسفل لوحة الإدارة.') }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        @foreach ($dashboard['my_tasks'] as $task)
                            <a href="{{ $task['url'] }}" class="block rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 transition hover:border-gray-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $task['title'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $task['number'] }}</p>
                                    </div>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses($task['status_tone'] ?? 'gray') }}">
                                        {{ $task['status_label'] }}
                                    </span>
                                </div>

                                @if (! empty($task['meta']))
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $task['meta'] }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            <section data-dashboard-personal-stats class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($dashboard['stats'] ?? [] as $stat)
                    <article class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white tabular-nums">{{ number_format($stat['value']) }}</p>
                            </div>

                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses($stat['tone'] ?? 'gray') }}">
                                {{ $stat['label'] }}
                            </span>
                        </div>

                        @if (! empty($stat['url']))
                            <div class="mt-3">
                                <x-filament::button tag="a" href="{{ $stat['url'] }}" color="gray" size="sm" outlined>
                                    {{ __('فتح') }}
                                </x-filament::button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('آخر مهامي') }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('أحدث المهام المرتبطة بك مباشرة.') }}</p>
                    </div>
                </div>

                @if (! empty($dashboard['recent_tasks']))
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        @foreach ($dashboard['recent_tasks'] as $task)
                            <a href="{{ $task['url'] }}" class="block rounded-2xl border border-gray-200/80 bg-gray-50/80 px-3 py-3 transition hover:border-gray-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $task['title'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $task['number'] }}</p>
                                    </div>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses($task['status_tone'] ?? 'gray') }}">
                                        {{ $task['status_label'] }}
                                    </span>
                                </div>

                                @if (! empty($task['meta']))
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $task['meta'] }}</p>
                                @endif

                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ $task['updated_at'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                        {{ __('لا توجد مهام حديثة لعرضها.') }}
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
