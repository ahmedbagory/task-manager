<x-filament-widgets::widget>
    <x-filament::section :heading="__('Task Breakdowns')">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @php
                $groups = [
                    __('Tasks By Department') => $breakdowns['by_department'] ?? collect(),
                    __('Tasks By Category') => $breakdowns['by_category'] ?? collect(),
                    __('Tasks By Employee') => $breakdowns['by_employee'] ?? collect(),
                    __('Tasks By Priority') => $breakdowns['by_priority'] ?? collect(),
                    __('Tasks By Status') => $breakdowns['by_status'] ?? collect(),
                    __('Tasks By Source') => $breakdowns['by_source'] ?? collect(),
                ];
            @endphp

            @foreach ($groups as $title => $items)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</h3>

                    <div class="mt-3 space-y-2">
                        @forelse ($items as $item)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate text-gray-700 dark:text-gray-200" title="{{ $item['label'] }}">
                                    {{ $item['label'] }}
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ $item['total'] }}
                                </span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No data for current filters.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
