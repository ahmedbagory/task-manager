<x-layouts.my-tasks :title="__('My Tasks')">
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('my-tasks.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="w-full sm:max-w-xs">
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Filter By Status') }}</label>
                <select
                    name="status"
                    id="status"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                {{ __('Apply') }}
            </button>
        </form>
    </section>

    <section class="mt-5 space-y-3">
        @forelse ($tasks as $task)
            @php
                $statusClass = match ($task->status->value) {
                    'new' => 'bg-slate-100 text-slate-700',
                    'pending_assignment' => 'bg-amber-100 text-amber-800',
                    'assigned' => 'bg-sky-100 text-sky-800',
                    'accepted' => 'bg-cyan-100 text-cyan-800',
                    'in_progress' => 'bg-indigo-100 text-indigo-800',
                    'wait_response' => 'bg-orange-100 text-orange-800',
                    'completed' => 'bg-emerald-100 text-emerald-800',
                    'cancelled', 'rejected' => 'bg-rose-100 text-rose-700',
                    default => 'bg-slate-100 text-slate-700',
                };

                $priorityClass = match ($task->priority->value) {
                    'low' => 'bg-slate-100 text-slate-700',
                    'medium' => 'bg-sky-100 text-sky-800',
                    'high' => 'bg-amber-100 text-amber-800',
                    'urgent' => 'bg-rose-100 text-rose-700',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $task->task_number }}</p>
                        <h2 class="mt-1 text-base font-semibold text-slate-900 sm:text-lg">{{ $task->title }}</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $task->description ? str($task->description)->limit(160) : __('No description provided.') }}
                        </p>
                    </div>

                    <a href="{{ route('my-tasks.show', $task) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        {{ __('Open') }}
                    </a>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full px-2.5 py-1 font-semibold {{ $statusClass }}">{{ __('Status') }}: {{ $task->status->label() }}</span>
                    <span class="rounded-full px-2.5 py-1 font-semibold {{ $priorityClass }}">{{ __('Priority') }}: {{ $task->priority->label() }}</span>
                    @if ($task->department)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{{ __('Department') }}: {{ $task->department->name }}</span>
                    @endif
                    @if ($task->due_at)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{{ __('Due') }}: {{ $task->due_at->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-slate-500 shadow-sm">
                {{ __('No assigned tasks found.') }}
            </div>
        @endforelse
    </section>

    <div class="mt-6">
        {{ $tasks->links() }}
    </div>
</x-layouts.my-tasks>
