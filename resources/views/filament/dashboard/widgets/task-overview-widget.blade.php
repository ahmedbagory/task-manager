<div data-dashboard-stats class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($stats as $stat)
        <article class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums {{ $stat['value_color'] }}">{{ $stat['value'] }}</p>
        </article>
    @endforeach
</div>
