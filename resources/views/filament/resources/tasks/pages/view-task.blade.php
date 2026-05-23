<x-filament-panels::page>
    @php
        $task = $this->getTask();
        $latestAssignment = $task->latestAssignment;
        $resolvedTargetUsers = $this->getResolvedTargetUsers();
        $activityFeed = $this->getActivityFeed();
        $previewItems = $this->getAttachmentPreviewItems();
        $comments = $task->comments->sortBy('created_at')->values();
        $hasTargets = $task->assignmentTargets->isNotEmpty();
        $hasDirectAssignee = $task->assignedToUser !== null;

        $statusClasses = match ($task->status->value) {
            'new' => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/15 dark:text-slate-200 dark:ring-slate-500/20',
            'pending_assignment' => 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-500/20',
            'assigned' => 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-500/20',
            'accepted' => 'bg-cyan-100 text-cyan-800 ring-cyan-200 dark:bg-cyan-500/15 dark:text-cyan-200 dark:ring-cyan-500/20',
            'in_progress' => 'bg-indigo-100 text-indigo-800 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-500/20',
            'wait_response' => 'bg-orange-100 text-orange-800 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-200 dark:ring-orange-500/20',
            'completed' => 'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/20',
            'cancelled', 'rejected' => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-200 dark:ring-rose-500/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/15 dark:text-slate-200 dark:ring-slate-500/20',
        };

        $priorityClasses = match ($task->priority->value) {
            'low' => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/15 dark:text-slate-200 dark:ring-slate-500/20',
            'medium' => 'bg-blue-100 text-blue-800 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-200 dark:ring-blue-500/20',
            'high' => 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-500/20',
            'urgent' => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-200 dark:ring-rose-500/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/15 dark:text-slate-200 dark:ring-slate-500/20',
        };
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#101827]">
            <div class="grid gap-6 p-6 lg:grid-cols-[1.45fr_.9fr] lg:p-7">
                <div class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                <span>{{ $task->task_number }}</span>
                                <span class="h-1 w-1 rounded-full bg-amber-500/70"></span>
                                <span>{{ $task->source->label() }}</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                {{ $task->title }}
                            </h1>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClasses }}">
                                {{ $task->status->label() }}
                            </span>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $priorityClasses }}">
                                {{ $task->priority->label() }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-[1.4rem] border border-slate-200/80 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/5">
                        <p class="text-sm leading-7 text-slate-700 dark:text-slate-200">
                            {{ $task->description ?: __('لا يوجد وصف مضاف للمهمة حتى الآن.') }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('القسم / الوحدة') }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ $task->department?->hierarchy_name ?? '—' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('التصنيف') }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ $task->category?->name ?? '—' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('الموعد المستهدف') }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ $task->due_at?->format('Y-m-d H:i') ?? '—' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('الموقع') }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ $task->location ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Right side cards --}}
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    {{-- Execution status card --}}
                    @if ($hasDirectAssignee)
                        {{-- A specific employee is actively assigned --}}
                        <div class="rounded-[1.4rem] border border-emerald-400/30 bg-slate-950 p-5 text-white shadow-lg shadow-slate-950/10 dark:border-emerald-500/20 dark:bg-[#0b1220]">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400/80">{{ __('معيّنة لـ') }}</p>
                            <p class="mt-3 text-lg font-semibold">{{ $task->assignedToUser->name }}</p>
                            <p class="mt-1 text-sm text-slate-300">{{ $task->assignedToUser->department?->hierarchy_name ?? '—' }}</p>
                            @if ($latestAssignment)
                                <div class="mt-3 flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-300">
                                        {{ $latestAssignment->status->label() }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $latestAssignment->assigned_at?->format('Y-m-d H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    @elseif ($hasTargets)
                        {{-- Task is assigned to targets but no one accepted yet --}}
                        <div class="rounded-[1.4rem] border border-amber-400/30 bg-slate-950 p-5 text-white shadow-lg shadow-slate-950/10 dark:border-amber-500/20 dark:bg-[#0b1220]">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300/80">{{ __('مسنَدة') }}</p>
                            <p class="mt-3 text-lg font-semibold">
                                {{ $resolvedTargetUsers->count() }} {{ __('موظف') }}
                            </p>
                            <p class="mt-1 text-sm text-slate-300">{{ __('بانتظار قبول أحد الموظفين') }}</p>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($task->assignmentTargets->take(3) as $target)
                                    <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-medium text-slate-200">
                                        {{ $target->target_name }}
                                    </span>
                                @endforeach
                                @if ($task->assignmentTargets->count() > 3)
                                    <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-medium text-slate-300">
                                        +{{ $task->assignmentTargets->count() - 3 }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @else
                        {{-- No assignment at all --}}
                        <div class="rounded-[1.4rem] border border-slate-200/80 bg-slate-950 p-5 text-white shadow-lg shadow-slate-950/10 dark:border-white/10 dark:bg-[#0b1220]">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300/80">{{ __('الإسناد') }}</p>
                            <p class="mt-3 text-lg font-semibold">{{ __('غير مسنَدة') }}</p>
                            <p class="mt-1 text-sm text-slate-300">{{ __('لم يتم تعيين أي موظف أو قسم بعد') }}</p>
                        </div>
                    @endif

                    <div class="rounded-[1.4rem] border border-slate-200/80 bg-slate-50/50 p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ __('التفاعل') }}</p>
                        <div class="mt-4 flex items-center gap-6">
                            <div>
                                <p class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $comments->count() }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('تعليق') }}</p>
                            </div>
                            <div>
                                <p class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $task->attachments->count() }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('مرفق') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.55fr_.95fr]">
            <div class="space-y-6">
                @include('filament.resources.tasks.pages.partials.attachment-preview', [
                    'task' => $task,
                    'previewItems' => $previewItems,
                ])

                @include('filament.resources.tasks.pages.partials.comment-thread', [
                    'comments' => $comments,
                ])
            </div>

            <div class="space-y-6">
                {{-- Assignment section --}}
                <section class="rounded-[1.6rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#101827]">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ __('الإسناد') }}</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('من المسنَد إليهم هذه المهمة') }}</p>
                        </div>
                        <div class="flex gap-2">
                            @if ($this->canShowAssignAction())
                                <button
                                    type="button"
                                    wire:click="mountAction('assign')"
                                    class="inline-flex items-center gap-2 rounded-full bg-amber-500 px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-amber-400"
                                >
                                    <x-filament::icon icon="heroicon-o-user-plus" class="h-4 w-4" />
                                    {{ $hasTargets || $hasDirectAssignee ? __('تعديل الإسناد') : __('تعيين') }}
                                </button>
                            @endif

                            @if ($this->canShowReassignAction())
                                <button
                                    type="button"
                                    wire:click="mountAction('reassign')"
                                    class="inline-flex items-center gap-2 rounded-full bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-500"
                                >
                                    <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                                    {{ __('إعادة التعيين') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        {{-- Direct assignee (if someone accepted) --}}
                        @if ($hasDirectAssignee)
                            <div class="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                                    {{ __('الموظف المنفذ') }}
                                </div>
                                <div class="mt-3 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-lg font-semibold text-slate-950 dark:text-white">{{ $task->assignedToUser->name }}</p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $task->assignedToUser->department?->hierarchy_name ?? '—' }}</p>
                                    </div>
                                    @if ($latestAssignment)
                                        <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10">
                                            {{ $latestAssignment->status->label() }}
                                        </span>
                                    @endif
                                </div>

                                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('بواسطة') }}</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ $latestAssignment?->assignedByUser?->name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('وقت التعيين') }}</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ $latestAssignment?->assigned_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                                    </div>
                                </dl>

                                @if (filled($latestAssignment?->note))
                                    <div class="mt-4 rounded-2xl bg-white/80 p-3 text-sm text-slate-700 dark:bg-white/5 dark:text-slate-200">
                                        {{ $latestAssignment->note }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Assignment targets --}}
                        @if ($hasTargets)
                            <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-white/5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('مسنَدة إلى') }}</p>
                                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-slate-950 px-2 text-xs font-semibold text-white dark:bg-white dark:text-slate-950">
                                        {{ $resolvedTargetUsers->count() }}
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($task->assignmentTargets as $target)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-white/8 dark:text-slate-200 dark:ring-white/10">
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ $target->target_type_label }}</span>
                                            {{ $target->target_name }}
                                        </span>
                                    @endforeach
                                </div>

                                @if (! $hasDirectAssignee)
                                    <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                                        {{ __('بانتظار قبول أحد الموظفين المستهدفين') }}
                                    </p>
                                @endif
                            </div>
                        @elseif (! $hasDirectAssignee)
                            {{-- Nothing assigned at all --}}
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center dark:border-white/10 dark:bg-white/5">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('لم يتم إسناد المهمة بعد.') }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('اضغط "تعيين" لإسناد المهمة لأقسام أو فروع أو موظفين.') }}</p>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Activity feed --}}
                <section class="rounded-[1.6rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#101827]">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-white/10">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ __('النشاط الأخير') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('سجل سريع للتعيين والحركة على المهمة') }}</p>
                    </div>

                    <div class="space-y-3 p-5">
                        @forelse ($activityFeed as $item)
                            <article class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-white/5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $item['title'] }}</p>
                                        @if (filled($item['meta']))
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $item['meta'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $item['at']?->format('Y-m-d H:i') }}</span>
                                </div>

                                @if (filled($item['body']))
                                    <p class="mt-3 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $item['body'] }}</p>
                                @endif
                            </article>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا يوجد نشاط مسجل حتى الآن.') }}</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
