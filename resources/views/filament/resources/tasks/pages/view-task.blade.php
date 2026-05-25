<x-filament-panels::page>
    @php
        $task = $this->getTask();
        $workflowStatus = $task->workflowStatus();
        $latestAssignment = $task->latestAssignment;
        $resolvedTargetUsers = $this->getResolvedTargetUsers();
        $assignees = $this->getAssignees();
        $reporter = $this->getReporter();
        $activityFeed = $this->getActivityFeed();
        $previewItems = $this->getAttachmentPreviewItems();
        $comments = $task->comments->sortBy('created_at')->values();
        $hasTargets = $task->hasValidAssignmentTargets();
        $hasDirectAssignee = $task->hasActiveAssignee() && $task->assignedToUser !== null;
        $assignmentAudienceCount = $assignees->count() ?: ($hasDirectAssignee ? 1 : 0);
        $targetPreview = $assignees->take(6);
        $remainingTargetCount = max($assignees->count() - $targetPreview->count(), 0);
        $infoItems = [
            ['label' => __('القسم / الوحدة'), 'value' => $task->department?->hierarchy_name ?? '—'],
            ['label' => __('التصنيف'), 'value' => $task->category?->name ?? '—'],
            ['label' => __('الموعد المستهدف'), 'value' => $task->due_at?->format('Y-m-d H:i') ?? '—'],
            ['label' => __('الموقع'), 'value' => $task->location ?: '—'],
            ['label' => __('المبلّغ / الطالب'), 'value' => trim(implode(' - ', array_filter([$reporter['name'] ?? null, $reporter['phone'] ?? null]))) ?: '—'],
        ];
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <x-filament::section
                compact
                :heading="$task->title"
                :description="$task->task_number"
            >
                <x-slot name="afterHeader">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge :color="$workflowStatus->color()">
                            {{ $task->workflowStatusLabel() }}
                        </x-filament::badge>

                        <x-filament::badge :color="$task->priority->color()">
                            {{ $task->priority->label() }}
                        </x-filament::badge>

                        <x-filament::badge :color="$task->source->color()">
                            {{ $task->source->label() }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                <div class="space-y-4">
                    <p class="text-sm leading-6 text-gray-700 dark:text-gray-200">
                        {{ $task->description ?: __('لا يوجد وصف مضاف للمهمة حتى الآن.') }}
                    </p>

                    <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($infoItems as $item)
                            <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                                <dd class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $item['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </x-filament::section>

            @include('filament.resources.tasks.pages.partials.attachment-preview', [
                'task' => $task,
                'previewItems' => $previewItems,
            ])

            @include('filament.resources.tasks.pages.partials.comment-thread', [
                'comments' => $comments,
            ])
        </div>

        <div class="space-y-6">
            <x-filament::section
                compact
                :heading="__('الإسناد')"
                :description="__('ملخص حالة الإسناد الحالية للمهمة')"
            >
                <div class="space-y-4">
                    <dl class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('المستهدفون') }}</dt>
                            <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $assignmentAudienceCount }}</dd>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('التعليقات') }}</dt>
                            <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $comments->count() }}</dd>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('المرفقات') }}</dt>
                            <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $task->attachments->count() }}</dd>
                        </div>
                    </dl>

                    @if ($assignees->isNotEmpty())
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('فريق التنفيذ') }}</p>
                                    <p class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ $assignees->pluck('name')->implode('، ') }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('المبلّغ') }}: {{ trim(implode(' - ', array_filter([$reporter['name'] ?? null, $reporter['phone'] ?? null]))) ?: '—' }}
                                    </p>
                                </div>

                                @if ($latestAssignment)
                                    <x-filament::badge :color="$latestAssignment->status->color()">
                                        {{ $latestAssignment->status->label() }}
                                    </x-filament::badge>
                                @endif
                            </div>

                            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('بواسطة') }}</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $latestAssignment?->assignedByUser?->name ?? '—' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('وقت التعيين') }}</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $latestAssignment?->assigned_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                                </div>
                            </dl>

                            @if (filled($latestAssignment?->note))
                                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
                                    {{ $latestAssignment->note }}
                                </div>
                            @endif
                        </div>
                    @elseif ($hasTargets)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('مسندة إلى') }}</p>
                                    <p class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ $resolvedTargetUsers->count() }} {{ __('موظف') }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Pending Acceptance') }}</p>
                                </div>

                                <x-filament::badge :color="$workflowStatus->color()">
                                    {{ $task->workflowStatusLabel() }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('غير مسندة') }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('لم يتم تعيين أي موظف أو قسم بعد.') }}
                            </p>
                        </div>
                    @endif

                    @if ($targetPreview->isNotEmpty())
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('الأسماء المستهدفة') }}</p>
                                <x-filament::badge color="gray">{{ $resolvedTargetUsers->count() }}</x-filament::badge>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($targetPreview as $user)
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $user->name }}
                                    </x-filament::badge>
                                @endforeach

                                @if ($remainingTargetCount > 0)
                                    <x-filament::badge color="gray" size="sm">
                                        +{{ $remainingTargetCount }}
                                    </x-filament::badge>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section
                compact
                :heading="__('النشاط الأخير')"
                :description="__('آخر حركة تمت على الإسناد أو المرفقات')"
            >
                <div class="space-y-3">
                    @forelse ($activityFeed as $item)
                        <article class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</p>

                                    @if (filled($item['meta']))
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['meta'] }}</p>
                                    @endif
                                </div>

                                <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $item['at']?->format('Y-m-d H:i') }}</p>
                            </div>

                            @if (filled($item['body']))
                                <p class="mt-3 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $item['body'] }}</p>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('لا يوجد نشاط مسجل حتى الآن.') }}</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
