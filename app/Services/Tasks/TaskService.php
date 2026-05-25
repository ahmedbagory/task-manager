<?php

namespace App\Services\Tasks;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Services\WhatsApp\TaskWhatsAppNotificationService;
use App\Support\RunsAfterCommit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TaskService
{
    use RunsAfterCommit;

    public function __construct(
        private readonly TaskNumberGenerator $taskNumberGenerator,
        private readonly TaskWhatsAppNotificationService $taskWhatsAppNotificationService,
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
        private readonly FcmNotificationService $fcmNotificationService,
        private readonly TaskAssigneeSyncService $taskAssigneeSyncService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManualTask(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor): Task {
            $assigneeIds = $this->pullAssigneeIds($data);
            unset($data['task_number'], $data['created_by'], $data['updated_by'], $data['source'], $data['status']);

            $data['task_number'] = $this->taskNumberGenerator->generate();
            $data['source'] = TaskSource::MANUAL->value;
            $data['status'] = $this->initialStatusForAssignees($assigneeIds)->value;
            $data['created_by'] = $actor->id;
            $data['updated_by'] = $actor->id;

            $task = Task::query()->create($data);

            $addedUserIds = [];

            if ($assigneeIds !== []) {
                $sync = $this->taskAssigneeSyncService->sync($task, $assigneeIds, $actor);
                $addedUserIds = $sync['added_ids'];
                $task = $task->fresh();
            }

            $this->runAfterCommit(function () use ($task, $addedUserIds, $actor): void {
                $freshTask = $this->shouldRunAfterCommitImmediately()
                    ? $task
                    : Task::query()->find($task->id);

                if ($freshTask) {
                    $this->notifyAssigneeUsers($freshTask, $addedUserIds, $actor, 'new_assignment');
                }
            });

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTaskFromWhatsAppMessage(WhatsappMessage $message, array $data = [], ?User $actor = null): Task
    {
        return DB::transaction(function () use ($message, $data, $actor): Task {
            $lockedMessage = WhatsappMessage::query()->lockForUpdate()->findOrFail($message->id);
            $assigneeIds = $this->pullAssigneeIds($data);

            if (filled($lockedMessage->task_id)) {
                return Task::query()->findOrFail($lockedMessage->task_id);
            }

            $title = (string) ($data['title'] ?? str($lockedMessage->body ?: 'WhatsApp issue report')->limit(100));
            $description = $data['description'] ?? $lockedMessage->body;
            $reporterPhone = $data['reported_by_phone'] ?? $lockedMessage->from_phone ?? $lockedMessage->contact?->phone;

            $task = Task::query()->create([
                'task_number' => $this->taskNumberGenerator->generate(),
                'title' => filled($title) ? $title : 'WhatsApp issue report',
                'description' => $description,
                'department_id' => $data['department_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'reported_by_user_id' => $data['reported_by_user_id'] ?? null,
                'whatsapp_contact_id' => $data['whatsapp_contact_id'] ?? $lockedMessage->contact_id,
                'reported_by_phone' => $reporterPhone,
                'priority' => $data['priority'] ?? 'medium',
                'status' => $this->initialStatusForAssignees($assigneeIds)->value,
                'source' => TaskSource::WHATSAPP->value,
                'location' => $data['location'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $addedUserIds = [];

            if ($assigneeIds !== [] && $actor) {
                $sync = $this->taskAssigneeSyncService->sync($task, $assigneeIds, $actor);
                $addedUserIds = $sync['added_ids'];
                $task = $task->fresh();
            }

            $lockedMessage->update(['task_id' => $task->id]);

            $this->runAfterCommit(function () use ($task, $addedUserIds, $actor): void {
                $freshTask = $this->shouldRunAfterCommitImmediately()
                    ? $task
                    : Task::query()->find($task->id);

                if ($freshTask) {
                    $this->notifyAssigneeUsers($freshTask, $addedUserIds, $actor, 'new_assignment');
                    $this->taskWhatsAppNotificationService->notifyTaskRegistered($freshTask);
                }
            });

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTask(Task $task, array $data, User $actor): Task
    {
        $assigneeIds = $this->pullAssigneeIds($data);
        unset($data['task_number'], $data['created_by'], $data['updated_by'], $data['source']);

        return DB::transaction(function () use ($task, $data, $actor, $assigneeIds): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $previousStatus = $lockedTask->status instanceof TaskStatus
                ? $lockedTask->status
                : TaskStatus::from((string) $lockedTask->status);
            $statusWasExplicitlyChanged = array_key_exists('status', $data) && filled($data['status']);

            $requestedStatus = $statusWasExplicitlyChanged
                ? TaskStatus::from((string) $data['status'])
                : $previousStatus;

            unset($data['status']);

            $lockedTask->fill($data);
            $lockedTask->updated_by = $actor->id;
            $lockedTask->save();

            $sync = $this->taskAssigneeSyncService->sync($lockedTask, $assigneeIds, $actor);
            $nextStatus = $this->resolveUpdatedStatus(
                currentStatus: $previousStatus,
                requestedStatus: $requestedStatus,
                assigneeIds: $assigneeIds,
                statusWasExplicitlyChanged: $statusWasExplicitlyChanged,
            );

            $this->applyStatusState($lockedTask, $nextStatus, $actor, $previousStatus);
            $lockedTask->save();

            $statusChanged = $previousStatus !== $nextStatus;

            if ($statusChanged) {
                TaskAssignmentHistory::query()->create([
                    'task_id' => $lockedTask->id,
                    'action' => 'status_changed',
                    'performed_by' => $actor->id,
                    'note' => __('تم تعديل حالة المهمة من :from إلى :to.', [
                        'from' => $previousStatus->label(),
                        'to' => $nextStatus->label(),
                    ]),
                ]);

                if (
                    $previousStatus === TaskStatus::COMPLETED
                    && in_array($nextStatus, [
                        TaskStatus::ASSIGNED,
                        TaskStatus::ACCEPTED,
                        TaskStatus::IN_PROGRESS,
                        TaskStatus::WAIT_RESPONSE,
                        TaskStatus::REOPENED,
                    ], true)
                ) {
                    $lockedTask->comments()->create([
                        'user_id' => $actor->id,
                        'comment' => __('تمت إعادة فتح المهمة يدويًا وإعادتها لفريق التنفيذ.'),
                        'is_internal' => false,
                    ]);
                }
            }

            $this->runAfterCommit(function () use ($lockedTask, $sync, $actor, $previousStatus, $nextStatus, $statusChanged): void {
                $freshTask = $this->shouldRunAfterCommitImmediately()
                    ? $lockedTask
                    : Task::query()->find($lockedTask->id);

                if (! $freshTask) {
                    return;
                }

                $this->notifyAssigneeUsers($freshTask, $sync['added_ids'], $actor, 'added_assignee');
                $this->dispatchManualStatusNotifications($freshTask, $actor, $previousStatus, $nextStatus, $statusChanged);
            });

            return $lockedTask->refresh();
        });
    }

    public function changeStatus(Task $task, TaskStatus|string $status, ?User $actor = null): Task
    {
        $targetStatus = $status instanceof TaskStatus ? $status : TaskStatus::from($status);

        return DB::transaction(function () use ($task, $targetStatus, $actor): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $wasCompleted = $lockedTask->status === TaskStatus::COMPLETED;

            $lockedTask->status = $targetStatus;
            $lockedTask->updated_by = $actor?->id;

            if ($targetStatus === TaskStatus::IN_PROGRESS && is_null($lockedTask->started_at)) {
                $lockedTask->started_at = now();
            }

            if ($targetStatus === TaskStatus::COMPLETED) {
                $lockedTask->completed_at = now();
            }

            if (in_array($targetStatus, [TaskStatus::CANCELLED, TaskStatus::REJECTED], true)) {
                $lockedTask->assigned_to_user_id = null;
            }

            $lockedTask->save();

            if ($targetStatus === TaskStatus::COMPLETED && (! $wasCompleted)) {
                $this->runAfterCommit(function () use ($lockedTask, $actor): void {
                    $freshTask = $this->shouldRunAfterCommitImmediately()
                        ? $lockedTask
                        : Task::query()->find($lockedTask->id);

                    if ($freshTask) {
                        $this->taskWhatsAppNotificationService->notifyTaskCompleted($freshTask);
                        $this->taskWorkflowNotificationService->notifyTaskCompletedToDispatchers($freshTask);
                        $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'completed', $actor);
                    }
                });
            }

            return $lockedTask->refresh();
        });
    }

    public function completeTask(Task $task, ?User $actor = null): Task
    {
        return $this->changeStatus($task, TaskStatus::COMPLETED, $actor);
    }

    public function cancelTask(Task $task, ?User $actor = null, ?string $reason = null): Task
    {
        return DB::transaction(function () use ($task, $actor, $reason): Task {
            $cancelledTask = $this->changeStatus($task, TaskStatus::CANCELLED, $actor);

            $reason = trim((string) $reason);

            if ($reason !== '') {
                $cancelledTask->description = trim(($cancelledTask->description ? $cancelledTask->description.PHP_EOL.PHP_EOL : '')."Cancellation reason: {$reason}");
                $cancelledTask->save();
            }

            return $cancelledTask->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function pullAssigneeIds(array &$data): array
    {
        return $this->taskAssigneeSyncService->normalizeIds(
            Arr::pull($data, 'assignee_ids', []),
        );
    }

    /**
     * @param  array<int, int>  $assigneeIds
     */
    private function initialStatusForAssignees(array $assigneeIds): TaskStatus
    {
        return $assigneeIds === []
            ? TaskStatus::NEW
            : TaskStatus::ASSIGNED;
    }

    /**
     * @param  array<int, int>  $assigneeIds
     */
    private function resolveUpdatedStatus(
        TaskStatus $currentStatus,
        TaskStatus $requestedStatus,
        array $assigneeIds,
        bool $statusWasExplicitlyChanged,
    ): TaskStatus {
        if ($statusWasExplicitlyChanged) {
            return $requestedStatus;
        }

        if ($assigneeIds === []) {
            return in_array($currentStatus, [
                TaskStatus::COMPLETED,
                TaskStatus::CANCELLED,
            ], true)
                ? $currentStatus
                : TaskStatus::NEW;
        }

        return in_array($currentStatus, [
            TaskStatus::NEW,
            TaskStatus::PENDING_ASSIGNMENT,
            TaskStatus::ASSIGNED,
        ], true)
            ? TaskStatus::ASSIGNED
            : $currentStatus;
    }

    private function applyStatusState(Task $task, TaskStatus $targetStatus, User $actor, TaskStatus $previousStatus): void
    {
        $task->status = $targetStatus;
        $task->updated_by = $actor->id;

        if ($targetStatus === TaskStatus::IN_PROGRESS && is_null($task->started_at)) {
            $task->started_at = now();
        }

        if ($targetStatus === TaskStatus::COMPLETED) {
            $task->completed_at = now();
        } elseif (in_array($targetStatus, [
            TaskStatus::NEW,
            TaskStatus::PENDING_ASSIGNMENT,
            TaskStatus::ASSIGNED,
            TaskStatus::ACCEPTED,
            TaskStatus::IN_PROGRESS,
            TaskStatus::WAIT_RESPONSE,
            TaskStatus::REOPENED,
            TaskStatus::AWAITING_REPORTER_CONFIRMATION,
        ], true)) {
            $task->completed_at = null;
        }

        if ($targetStatus === TaskStatus::AWAITING_REPORTER_CONFIRMATION) {
            $task->resolution_submitted_by_user_id = $actor->id;
            $task->resolution_submitted_at = now();
            $task->reporter_confirmation_status = 'pending';
            $task->reporter_confirmed_by_user_id = null;
            $task->reporter_confirmed_at = null;
            $task->completed_at = null;
        }

        if (in_array($targetStatus, [
            TaskStatus::NEW,
            TaskStatus::PENDING_ASSIGNMENT,
            TaskStatus::ASSIGNED,
            TaskStatus::ACCEPTED,
            TaskStatus::IN_PROGRESS,
            TaskStatus::WAIT_RESPONSE,
            TaskStatus::REOPENED,
        ], true)) {
            $task->reporter_confirmation_status = null;
            $task->reporter_confirmed_by_user_id = null;
            $task->reporter_confirmed_at = null;
            $task->resolution_submitted_by_user_id = null;
            $task->resolution_submitted_at = null;
        }
    }

    private function dispatchManualStatusNotifications(
        Task $task,
        User $actor,
        TaskStatus $previousStatus,
        TaskStatus $nextStatus,
        bool $statusChanged,
    ): void {
        if (! $statusChanged) {
            return;
        }

        if ($nextStatus === TaskStatus::AWAITING_REPORTER_CONFIRMATION) {
            $reporter = $this->resolveReporterUser($task);

            if ($reporter) {
                $this->taskWorkflowNotificationService->notifyReporterConfirmationRequested($task, $reporter);
                $this->fcmNotificationService->notifyReporterConfirmationRequested($task, $reporter);
            }

            return;
        }

        if ($nextStatus === TaskStatus::COMPLETED && $previousStatus !== TaskStatus::COMPLETED) {
            $this->taskWhatsAppNotificationService->notifyTaskCompleted($task);
            $this->taskWorkflowNotificationService->notifyTaskCompletedToDispatchers($task);
            $this->fcmNotificationService->notifyDispatchersTaskUpdate($task, 'completed', $actor);

            return;
        }

        if (
            $previousStatus === TaskStatus::COMPLETED
            && in_array($nextStatus, [
                TaskStatus::ASSIGNED,
                TaskStatus::ACCEPTED,
                TaskStatus::IN_PROGRESS,
                TaskStatus::WAIT_RESPONSE,
                TaskStatus::REOPENED,
            ], true)
        ) {
            $assignees = app(TaskAccessService::class)->resolveAssignees($task);

            if ($assignees->isNotEmpty()) {
                $this->taskWorkflowNotificationService->notifyTaskReopenedToAssignees($task, $assignees, $actor);
                $this->fcmNotificationService->notifyTaskReopenedToAssignees($task, $assignees->pluck('id')->map(fn ($id): int => (int) $id)->all(), $actor);
            }
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function notifyAssigneeUsers(Task $task, array $userIds, ?User $actor, string $context): void
    {
        $uniqueUserIds = collect($userIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($uniqueUserIds === []) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $uniqueUserIds)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->taskWorkflowNotificationService->notifyTaskAssignedToUsers(
            task: $task,
            recipients: $recipients,
            actor: $actor,
            context: $context,
        );
        $this->fcmNotificationService->notifyTaskTargetsAssigned($task, $uniqueUserIds, $actor, $context);
    }

    private function resolveReporterUser(Task $task): ?User
    {
        $task->loadMissing(['reportedByUser', 'whatsappContact.user']);

        if ($task->reportedByUser) {
            return $task->reportedByUser;
        }

        if ($task->whatsappContact?->user) {
            return $task->whatsappContact->user;
        }

        return null;
    }
}
