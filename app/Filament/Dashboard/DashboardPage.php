<?php

namespace App\Filament\Dashboard;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskStatus;
use App\Enums\WhatsappMessageDirection;
use App\Filament\Pages\WhatsAppSession;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Reports\TaskReportService;
use App\Services\Tasks\TaskAccessService;
use App\Services\WhatsApp\WhatsappBridgeProcessService;
use App\Services\WhatsApp\WhatsappBridgeStatusService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardPage extends BaseDashboard
{
    protected string $view = 'filament.dashboard.dashboard-page';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @var array<string, mixed>
     */
    public array $dashboard = [];

    public function mount(): void
    {
        $this->refreshDashboardState();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function refreshDashboardState(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $managementRole = app(TaskAccessService::class)->managementRole($user);

        $this->dashboard = $managementRole
            ? $this->buildManagementDashboard($user, $managementRole)
            : $this->buildEmployeeDashboard($user);
    }

    public function restartBridgeAction(): Action
    {
        return Action::make('restartBridge')
            ->label(__('إعادة تشغيل البريدج'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('إعادة تشغيل بريدج واتساب'))
            ->modalDescription(__('سيتم إعادة تشغيل عملية البريدج دون حذف الجلسة الحالية.'))
            ->authorize(fn (): bool => Auth::user()?->can('settings.api.manage') ?? false)
            ->action(function (): void {
                $service = app(WhatsappBridgeProcessService::class);

                if (! $service->pm2Exists()) {
                    Notification::make()
                        ->danger()
                        ->title(__('PM2 غير متوفر'))
                        ->body(__('لم يتم العثور على PM2 في المسار: ') . $service->getPm2Bin())
                        ->send();

                    return;
                }

                $result = $service->restart();

                Notification::make()
                    ->title($result['success'] ? __('تمت إعادة التشغيل') : __('فشلت إعادة التشغيل'))
                    ->body($result['success'] ? __('تمت إعادة تشغيل البريدج بنجاح.') : ($result['error'] ?: __('حدث خطأ غير معروف أثناء إعادة التشغيل.')))
                    ->color($result['success'] ? 'success' : 'danger')
                    ->send();

                sleep(3);
                $this->refreshDashboardState();
            });
    }

    public function reconnectBridgeAction(): Action
    {
        return Action::make('reconnectBridge')
            ->label(__('إعادة الربط'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('إعادة الربط أو إظهار QR'))
            ->modalDescription(__('سيتم إعادة تشغيل البريدج ومحاولة إظهار QR جديد إذا كانت الجلسة تحتاج ربطًا.'))
            ->authorize(fn (): bool => Auth::user()?->can('settings.api.manage') ?? false)
            ->action(function (): void {
                $service = app(WhatsappBridgeProcessService::class);

                if (! $service->pm2Exists()) {
                    Notification::make()
                        ->danger()
                        ->title(__('PM2 غير متوفر'))
                        ->body(__('لم يتم العثور على PM2 في المسار: ') . $service->getPm2Bin())
                        ->send();

                    return;
                }

                $result = $service->restart();

                Notification::make()
                    ->title($result['success'] ? __('تمت إعادة تهيئة الجلسة') : __('تعذر إعادة الربط'))
                    ->body($result['success'] ? __('أعد تحميل الصفحة خلال ثوانٍ لعرض QR إذا كان مطلوبًا.') : ($result['error'] ?: __('تعذر إعادة تشغيل البريدج.')))
                    ->color($result['success'] ? 'success' : 'danger')
                    ->send();

                sleep(5);
                $this->refreshDashboardState();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'dashboard' => $this->dashboard,
            'clockLocale' => app()->getLocale() === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-US',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmployeeDashboard(User $user): array
    {
        $taskQuery = $this->visibleTaskQuery($user);
        $recentTasks = (clone $taskQuery)
            ->with(['department.parent'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $newTasks = $this->countByStatuses(clone $taskQuery, [
            TaskStatus::NEW->value,
            TaskStatus::PENDING_ASSIGNMENT->value,
            TaskStatus::ASSIGNED->value,
            TaskStatus::ACCEPTED->value,
            TaskStatus::REOPENED->value,
        ]);

        $inProgressTasks = $this->countByStatuses(clone $taskQuery, [
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::WAIT_RESPONSE->value,
        ]);

        $overdueTasks = $this->applyOverdueScope(clone $taskQuery)->count();
        $awaitingConfirmationTasks = (clone $taskQuery)
            ->where('status', TaskStatus::AWAITING_REPORTER_CONFIRMATION->value)
            ->count();

        return [
            'role' => 'employee',
            'user_name' => $user->name,
            'headline' => __('مرحبًا، :name', ['name' => $user->name]),
            'summary' => __('هذه صورة سريعة لما يحتاج انتباهك اليوم.'),
            'top_pills' => [
                ['label' => __('آخر تحديث'), 'value' => now()->format('H:i')],
                ['label' => __('المهام المرئية'), 'value' => number_format((clone $taskQuery)->count())],
            ],
            'stats' => [
                [
                    'label' => __('مهامي الجديدة'),
                    'value' => $newTasks,
                    'tone' => 'info',
                    'url' => $this->taskListUrlForStatus(),
                ],
                [
                    'label' => __('مهامي قيد التنفيذ'),
                    'value' => $inProgressTasks,
                    'tone' => 'primary',
                    'url' => $this->taskListUrlForStatus(TaskStatus::IN_PROGRESS->value),
                ],
                [
                    'label' => __('مهامي المتأخرة'),
                    'value' => $overdueTasks,
                    'tone' => 'danger',
                    'url' => $this->taskListUrlForStatus(extra: [
                        'tableSort' => 'due_at',
                        'tableSortDirection' => 'asc',
                    ]),
                ],
                [
                    'label' => __('بانتظار تأكيد صاحب الطلب'),
                    'value' => $awaitingConfirmationTasks,
                    'tone' => 'warning',
                    'url' => $this->taskListUrlForStatus(TaskStatus::AWAITING_REPORTER_CONFIRMATION->value),
                ],
            ],
            'recent_tasks' => $this->mapTasks($recentTasks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildManagementDashboard(User $user, string $managementRole): array
    {
        $now = now();
        $reportService = app(TaskReportService::class);
        $overview = $reportService->getOverview(Task::query());

        $openTasks = Task::query()
            ->whereNotIn('status', $this->terminalStatuses())
            ->count();

        $pendingAssignmentTasks = $this->pendingAssignmentQuery()->count();
        $awaitingReporterTasks = Task::query()
            ->where('status', TaskStatus::AWAITING_REPORTER_CONFIRMATION->value)
            ->count();

        $completedToday = Task::query()
            ->where('status', TaskStatus::COMPLETED->value)
            ->whereDate('completed_at', $now->toDateString())
            ->count();

        $completedThisWeek = Task::query()
            ->where('status', TaskStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->count();

        return [
            'role' => 'management',
            'management_role' => $managementRole,
            'user_name' => $user->name,
            'headline' => __('مرحبًا، :name', ['name' => $user->name]),
            'summary' => __('يوجد :open مهمة مفتوحة، :overdue متأخرة، :pending بانتظار الإسناد.', [
                'open' => number_format($openTasks),
                'overdue' => number_format((int) ($overview['overdue_tasks'] ?? 0)),
                'pending' => number_format($pendingAssignmentTasks),
            ]),
            'top_pills' => [
                ['label' => __('الدور الحالي'), 'value' => $managementRole === 'admin' ? __('إدارة كاملة') : __('إدارة تشغيل')],
                ['label' => __('مكتملة هذا الأسبوع'), 'value' => number_format($completedThisWeek)],
                ['label' => __('متوسط الإنجاز'), 'value' => $this->formatDuration($overview['average_completion_seconds'] ?? null)],
            ],
            'kpis' => [
                [
                    'label' => __('إجمالي المهام المفتوحة'),
                    'value' => $openTasks,
                    'tone' => 'gray',
                    'url' => $this->taskListUrl(),
                ],
                [
                    'label' => __('مهام متأخرة'),
                    'value' => (int) ($overview['overdue_tasks'] ?? 0),
                    'tone' => 'danger',
                    'url' => $this->taskListUrl(extra: [
                        'tableSort' => 'due_at',
                        'tableSortDirection' => 'asc',
                    ]),
                ],
                [
                    'label' => __('بانتظار الإسناد'),
                    'value' => $pendingAssignmentTasks,
                    'tone' => 'warning',
                    'url' => $this->taskListUrl([
                        'status' => ['value' => TaskStatus::PENDING_ASSIGNMENT->value],
                    ]),
                ],
                [
                    'label' => __('بانتظار رد صاحب الطلب'),
                    'value' => $awaitingReporterTasks,
                    'tone' => 'warning',
                    'url' => $this->taskListUrl([
                        'status' => ['value' => TaskStatus::AWAITING_REPORTER_CONFIRMATION->value],
                    ]),
                ],
                [
                    'label' => __('قيد التنفيذ'),
                    'value' => (int) ($overview['in_progress_tasks'] ?? 0),
                    'tone' => 'primary',
                    'url' => $this->taskListUrl([
                        'status' => ['value' => TaskStatus::IN_PROGRESS->value],
                    ]),
                ],
                [
                    'label' => __('مكتملة اليوم'),
                    'value' => $completedToday,
                    'tone' => 'success',
                    'meta' => __('هذا الأسبوع: :count', ['count' => number_format($completedThisWeek)]),
                    'url' => $this->taskListUrl([
                        'status' => ['value' => TaskStatus::COMPLETED->value],
                    ]),
                ],
                [
                    'label' => __('متوسط وقت الإنجاز'),
                    'value' => $this->formatDuration($overview['average_completion_seconds'] ?? null),
                    'tone' => 'info',
                ],
            ],
            'needs_action' => $this->buildNeedsActionItems(),
            'team_workload' => $this->buildTeamWorkloadItems(),
            'departments' => $this->buildDepartmentDemandItems(),
            'whatsapp' => $this->buildWhatsappOperations(),
            'recent_activity' => $this->buildRecentActivityItems(),
            'my_tasks' => $this->mapTasks(
                $this->directUserTaskQuery($user)
                    ->with(['department.parent'])
                    ->orderByDesc('updated_at')
                    ->limit(4)
                    ->get()
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildNeedsActionItems(): array
    {
        $veryOverdueCount = Task::query()
            ->whereNotIn('status', $this->terminalStatuses())
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->subDays(3))
            ->count();

        $rejectedCount = Task::query()->where('status', TaskStatus::REJECTED->value)->count();
        $reopenedCount = Task::query()->where('status', TaskStatus::REOPENED->value)->count();
        $waitingTooLongCount = Task::query()
            ->where('status', TaskStatus::AWAITING_REPORTER_CONFIRMATION->value)
            ->where(function (Builder $query): void {
                $query
                    ->where('resolution_submitted_at', '<', now()->subDays(2))
                    ->orWhere(function (Builder $fallbackQuery): void {
                        $fallbackQuery
                            ->whereNull('resolution_submitted_at')
                            ->where('updated_at', '<', now()->subDays(2));
                    });
            })
            ->count();

        return [
            [
                'label' => __('مهام بدون مكلف'),
                'count' => $this->pendingAssignmentQuery()->count(),
                'tone' => 'warning',
                'actions' => [
                    [
                        'label' => __('فتح القائمة'),
                        'url' => $this->taskListUrl([
                            'status' => ['value' => TaskStatus::PENDING_ASSIGNMENT->value],
                        ]),
                    ],
                ],
            ],
            [
                'label' => __('مهام متأخرة جدًا'),
                'count' => $veryOverdueCount,
                'tone' => 'danger',
                'actions' => [
                    [
                        'label' => __('الأكثر تأخرًا'),
                        'url' => $this->taskListUrl(extra: [
                            'tableSort' => 'due_at',
                            'tableSortDirection' => 'asc',
                        ]),
                    ],
                ],
            ],
            [
                'label' => __('مرفوضة أو معاد فتحها'),
                'count' => $rejectedCount + $reopenedCount,
                'tone' => 'warning',
                'actions' => [
                    [
                        'label' => __('معاد فتحها'),
                        'url' => $this->taskListUrl([
                            'status' => ['value' => TaskStatus::REOPENED->value],
                        ]),
                    ],
                    [
                        'label' => __('مرفوضة'),
                        'url' => $this->taskListUrl([
                            'status' => ['value' => TaskStatus::REJECTED->value],
                        ]),
                    ],
                ],
            ],
            [
                'label' => __('بانتظار تأكيد صاحب الطلب لفترة طويلة'),
                'count' => $waitingTooLongCount,
                'tone' => 'warning',
                'actions' => [
                    [
                        'label' => __('فتح القائمة'),
                        'url' => $this->taskListUrl([
                            'status' => ['value' => TaskStatus::AWAITING_REPORTER_CONFIRMATION->value],
                        ]),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTeamWorkloadItems(): array
    {
        $terminalStatuses = $this->terminalStatuses();
        $rows = Task::query()
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to_user_id')
            ->whereNotNull('tasks.assigned_to_user_id')
            ->selectRaw('users.id as user_id, users.name as user_name')
            ->selectRaw('SUM(CASE WHEN tasks.status NOT IN (?, ?, ?) THEN 1 ELSE 0 END) as open_tasks', $terminalStatuses)
            ->selectRaw(
                'SUM(CASE WHEN tasks.due_at IS NOT NULL AND tasks.due_at < ? AND tasks.status NOT IN (?, ?, ?) THEN 1 ELSE 0 END) as overdue_tasks',
                array_merge([now()], $terminalStatuses),
            )
            ->selectRaw('SUM(CASE WHEN tasks.status = ? THEN 1 ELSE 0 END) as in_progress_tasks', [TaskStatus::IN_PROGRESS->value])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('open_tasks')
            ->limit(5)
            ->get();

        return $rows
            ->filter(fn (object $row): bool => filled($row->user_name))
            ->map(fn (object $row): array => [
                'name' => (string) $row->user_name,
                'open_tasks' => (int) $row->open_tasks,
                'overdue_tasks' => (int) $row->overdue_tasks,
                'in_progress_tasks' => (int) $row->in_progress_tasks,
                'url' => $this->taskListUrl([
                    'assigned_to_user_id' => ['value' => $row->user_id],
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDepartmentDemandItems(): array
    {
        $rows = Task::query()
            ->leftJoin('departments', 'departments.id', '=', 'tasks.department_id')
            ->whereNotIn('tasks.status', $this->terminalStatuses())
            ->selectRaw("tasks.department_id as department_id, COALESCE(NULLIF(departments.name, ''), NULLIF(tasks.location, ''), 'غير محدد') as label, COUNT(*) as total")
            ->groupBy('tasks.department_id')
            ->groupByRaw("COALESCE(NULLIF(departments.name, ''), NULLIF(tasks.location, ''), 'غير محدد')")
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return $rows
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->total,
                'url' => $row->department_id
                    ? $this->taskListUrl([
                        'department_id' => ['value' => $row->department_id],
                    ])
                    : $this->taskListUrl(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWhatsappOperations(): array
    {
        $bridgeStatus = app(WhatsappBridgeStatusService::class)->current();
        $canManageSession = Auth::user()?->can('settings.api.manage') ?? false;
        $latestMessage = WhatsappMessage::query()
            ->with(['contact', 'task'])
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->first();

        return [
            'status_label' => (string) ($bridgeStatus['label'] ?? __('غير متصل')),
            'status_tone' => (string) ($bridgeStatus['badge'] ?? 'gray'),
            'status_hint' => (string) ($bridgeStatus['status_hint'] ?? ''),
            'converted_tasks' => WhatsappMessage::query()
                ->where('direction', WhatsappMessageDirection::INBOUND->value)
                ->whereNotNull('task_id')
                ->count(),
            'unprocessed_messages' => WhatsappMessage::query()
                ->where('direction', WhatsappMessageDirection::INBOUND->value)
                ->whereNull('task_id')
                ->count(),
            'last_message' => $latestMessage ? [
                'summary' => $this->whatsappMessageSummary($latestMessage),
                'meta' => $this->whatsappMessageMeta($latestMessage),
                'url' => $latestMessage->task
                    ? TaskResource::getUrl('view', ['record' => $latestMessage->task])
                    : WhatsappMessageResource::getUrl('index'),
            ] : null,
            'open_inbox_url' => WhatsappMessageResource::getUrl('index'),
            'session_url' => WhatsAppSession::getUrl(),
            'qr_url' => WhatsAppSession::getUrl() . '#whatsapp-session-qr',
            'can_manage_session' => $canManageSession,
            'show_qr_button' => $canManageSession && (($bridgeStatus['qr_available'] ?? false) === true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentActivityItems(): array
    {
        $items = collect();

        Task::query()
            ->latest('created_at')
            ->limit(2)
            ->get()
            ->each(function (Task $task) use ($items): void {
                $items->push([
                    'key' => 'task-created-' . $task->id,
                    'title' => __('تم إنشاء مهمة'),
                    'description' => $task->title,
                    'meta' => $task->displayNumber(),
                    'at' => $task->created_at,
                    'url' => TaskResource::getUrl('view', ['record' => $task]),
                ]);
            });

        TaskAssignmentHistory::query()
            ->with(['task', 'toUser', 'performedByUser'])
            ->whereIn('action', ['assigned', 'reassigned'])
            ->latest('created_at')
            ->limit(2)
            ->get()
            ->each(function (TaskAssignmentHistory $history) use ($items): void {
                if (! $history->task) {
                    return;
                }

                $items->push([
                    'key' => 'assignment-' . $history->id,
                    'title' => $history->action === 'reassigned' ? __('تمت إعادة إسناد مهمة') : __('تم إسناد مهمة'),
                    'description' => $history->task->title,
                    'meta' => collect([
                        $history->toUser?->name,
                        $history->performedByUser?->name,
                    ])->filter()->implode(' • '),
                    'at' => $history->created_at,
                    'url' => TaskResource::getUrl('view', ['record' => $history->task]),
                ]);
            });

        Task::query()
            ->whereNotIn('status', $this->terminalStatuses())
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(1)
            ->get()
            ->each(function (Task $task) use ($items): void {
                $items->push([
                    'key' => 'task-overdue-' . $task->id,
                    'title' => __('تأخرت مهمة'),
                    'description' => $task->title,
                    'meta' => $task->due_at?->format('Y-m-d H:i'),
                    'at' => $task->due_at ?? $task->updated_at,
                    'url' => TaskResource::getUrl('view', ['record' => $task]),
                ]);
            });

        Task::query()
            ->where('status', TaskStatus::COMPLETED->value)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit(1)
            ->get()
            ->each(function (Task $task) use ($items): void {
                $items->push([
                    'key' => 'task-completed-' . $task->id,
                    'title' => __('اكتملت مهمة'),
                    'description' => $task->title,
                    'meta' => $task->displayNumber(),
                    'at' => $task->completed_at ?? $task->updated_at,
                    'url' => TaskResource::getUrl('view', ['record' => $task]),
                ]);
            });

        WhatsappMessage::query()
            ->with('task')
            ->where('direction', WhatsappMessageDirection::INBOUND->value)
            ->whereNotNull('task_id')
            ->latest('updated_at')
            ->limit(1)
            ->get()
            ->each(function (WhatsappMessage $message) use ($items): void {
                $items->push([
                    'key' => 'whatsapp-task-' . $message->id,
                    'title' => __('رسالة واتساب تحولت لمهمة'),
                    'description' => $message->task?->title ?? __('محادثة واتساب'),
                    'meta' => $this->whatsappMessageMeta($message),
                    'at' => $message->updated_at ?? $message->created_at,
                    'url' => $message->task
                        ? TaskResource::getUrl('view', ['record' => $message->task])
                        : WhatsappMessageResource::getUrl('index'),
                ]);
            });

        return $items
            ->sortByDesc(fn (array $item) => optional($item['at'])->getTimestamp() ?? 0)
            ->take(5)
            ->map(fn (array $item): array => [
                ...$item,
                'at_label' => $this->formatDateTime($item['at'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function mapTasks(Collection $tasks): array
    {
        return $tasks
            ->map(function (Task $task): array {
                $status = $task->workflowStatus();

                return [
                    'title' => $task->title,
                    'number' => $task->displayNumber(),
                    'status_label' => $task->workflowStatusLabel(),
                    'status_tone' => $status->color(),
                    'meta' => collect([
                        $task->department?->hierarchy_name ?? $task->location,
                        $task->due_at ? __('الاستحقاق :date', ['date' => $task->due_at->format('Y-m-d H:i')]) : null,
                    ])->filter()->implode(' • '),
                    'updated_at' => $this->formatDateTime($task->updated_at),
                    'url' => TaskResource::getUrl('view', ['record' => $task]),
                ];
            })
            ->all();
    }

    private function visibleTaskQuery(User $user): Builder
    {
        $query = Task::query();

        app(TaskAccessService::class)->applyVisibleToUserScope($query, $user);

        return $query;
    }

    private function directUserTaskQuery(User $user): Builder
    {
        return Task::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('assigned_to_user_id', $user->id)
                    ->orWhere('reported_by_user_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery->where('assigned_to_user_id', $user->id));
            });
    }

    private function pendingAssignmentQuery(): Builder
    {
        $recognizedTargets = array_values(array_unique([
            ...TaskAssignmentTarget::userTargetTypes(),
            ...TaskAssignmentTarget::departmentTargetTypes(),
            TaskAssignmentTarget::LEGACY_ALL,
        ]));

        return Task::query()
            ->whereNotIn('status', $this->terminalStatuses())
            ->where(function (Builder $query) use ($recognizedTargets): void {
                $query
                    ->where('status', TaskStatus::PENDING_ASSIGNMENT->value)
                    ->orWhere(function (Builder $innerQuery) use ($recognizedTargets): void {
                        $innerQuery
                            ->whereNull('assigned_to_user_id')
                            ->whereDoesntHave('assignments', fn (Builder $assignmentQuery) => $assignmentQuery->whereIn('status', [
                                TaskAssignmentStatus::ASSIGNED->value,
                                TaskAssignmentStatus::ACCEPTED->value,
                            ]))
                            ->whereDoesntHave('assignmentTargets', fn (Builder $targetQuery) => $targetQuery->whereIn('target_type', $recognizedTargets));
                    });
            });
    }

    private function applyOverdueScope(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', $this->terminalStatuses());
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function countByStatuses(Builder $query, array $statuses): int
    {
        return $query->whereIn('status', $statuses)->count();
    }

    /**
     * @return array<int, string>
     */
    private function terminalStatuses(): array
    {
        return [
            TaskStatus::COMPLETED->value,
            TaskStatus::CANCELLED->value,
            TaskStatus::REJECTED->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $extra
     */
    private function taskListUrl(array $filters = [], array $extra = []): string
    {
        $parameters = $extra;

        if ($filters !== []) {
            $parameters['tableFilters'] = $filters;
        }

        return TaskResource::getUrl('index', $parameters);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function taskListUrlForStatus(?string $status = null, array $extra = []): string
    {
        $filters = [];

        if ($status) {
            $filters['status'] = ['value' => $status];
        }

        return $this->taskListUrl($filters, $extra);
    }

    private function formatDuration(float|int|null $seconds): string
    {
        if (! is_numeric($seconds) || $seconds <= 0) {
            return __('غير متاح');
        }

        $seconds = (int) round($seconds);

        if ($seconds < 3600) {
            return __(':minutes د', ['minutes' => max(1, (int) round($seconds / 60))]);
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours < 24) {
            return __(':hours س :minutes د', [
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }

        return __(':days يوم', ['days' => intdiv($hours, 24)]);
    }

    private function formatDateTime(Carbon|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $date->timezone(config('app.timezone'))->format('Y-m-d H:i');
    }

    private function whatsappMessageSummary(WhatsappMessage $message): string
    {
        if (filled($message->body)) {
            return Str::limit((string) $message->body, 80);
        }

        return match ($message->message_type) {
            'image' => __('صورة'),
            'audio' => __('رسالة صوتية'),
            'document' => __('مستند'),
            default => __('رسالة واتساب'),
        };
    }

    private function whatsappMessageMeta(WhatsappMessage $message): string
    {
        return collect([
            $message->group_name,
            $message->contact?->name,
            $message->from_phone ?: $message->to_phone,
            $this->formatDateTime($message->received_at ?? $message->sent_at ?? $message->created_at),
        ])->filter()->implode(' • ');
    }
}
