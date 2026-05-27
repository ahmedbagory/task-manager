<x-filament-panels::page>
    @php
        $recentLogs = \App\Models\ScheduledNotificationLog::query()
            ->with('rule')
            ->latest('sent_at')
            ->limit(20)
            ->get();
    @endphp

    <div class="space-y-6">
        {{ $this->table }}

        @if ($recentLogs->isNotEmpty())
            <x-filament::section
                compact
                :heading="__('سجل الإرسال')"
                :description="__('آخر 20 عملية إرسال مجدولة')"
                collapsible
                collapsed
            >
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($recentLogs as $log)
                        <div class="flex items-center justify-between gap-4 py-3 px-1">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    @if ($log->status === 'sent')
                                        <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 text-success-500" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-x-circle" class="h-4 w-4 text-danger-500" />
                                    @endif
                                    <span class="text-sm font-medium text-gray-950 dark:text-white truncate">{{ $log->title }}</span>
                                </div>
                                <div class="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $log->rule?->name ?? '—' }}</span>
                                    <span>{{ $log->recipients_count }} {{ __('مستلم') }}</span>
                                    @if ($log->error)
                                        <span class="text-danger-600 dark:text-danger-400 truncate max-w-[200px]">{{ $log->error }}</span>
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
                                {{ $log->sent_at?->diffForHumans() ?? '—' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
