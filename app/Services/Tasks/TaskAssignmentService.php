<?php

namespace App\Services\Tasks;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Support\RunsAfterCommit;
use App\Services\WhatsApp\TaskWhatsAppNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskAssignmentService
{
    use RunsAfterCommit;

    public function __construct(
        private readonly TaskWhatsAppNotificationService $taskWhatsAppNotificationService,
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
        private readonly FcmNotificationService $fcmNotificationService,
        private readonly TaskAssignmentTargetResolver $taskAssignmentTargetResolver,
        private readonly TaskAccessService $taskAccessService,
    ) {}

    public function assignTask(Task $task, int $assignedToUserId, ?User $assignedBy = null, ?string $note = null): TaskAssignment
    {
        $assignment = DB::transaction(function () use ($task, $assignedToUserId, $assignedBy, $note): TaskAssignment {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if (in_array($lockedTask->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true)) {
                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'Completed or cancelled tasks cannot be assigned.',
                ]);
            }

            $activeAssignmentExists = $lockedTask->assignments()
                ->whereIn('status', [TaskAssignmentStatus::ASSIGNED->value, TaskAssignmentStatus::ACCEPTED->value])
                ->exists();

            if ($activeAssignmentExists) {
                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'Task already has an active assignment.',
                ]);
            }

            User::query()->findOrFail($assignedToUserId);

            $assignment = $lockedTask->assignments()->create([
                'assigned_to_user_id' => $assignedToUserId,
                'assigned_by_user_id' => $assignedBy?->id,
                'note' => $note,
                'status' => TaskAssignmentStatus::ASSIGNED->value,
                'assigned_at' => now(),
            ]);

            $lockedTask->forceFill([
                'assigned_to_user_id' => $assignedToUserId,
                'status' => TaskStatus::ASSIGNED->value,
                'updated_by' => $assignedBy?->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'assigned',
                'to_user_id' => $assignedToUserId,
                'performed_by' => $assignedBy?->id,
                'note' => $note,
            ]);

            return $assignment->refresh();
        });

        $freshTask = Task::query()->find($assignment->task_id);
        $freshAssignment = TaskAssignment::query()
            ->whereKey($assignment->id)
            ->with(['assignedToUser', 'assignedByUser'])
            ->first();

        if ($freshTask) {
            $this->taskWhatsAppNotificationService->notifyTaskAssigned($freshTask);

            if ($freshAssignment) {
                $this->taskWorkflowNotificationService->notifyTaskAssignedToEmployee(
                    task: $freshTask,
                    assignment: $freshAssignment,
                    context: 'new_assignment',
                );

                if ($freshAssignment->assignedToUser) {
                    $this->fcmNotificationService->notifyNewTaskAssigned(
                        task: $freshTask,
                        assignee: $freshAssignment->assignedToUser,
                        actor: $assignedBy,
                        context: 'new_assignment',
                    );
                }
            }
        }

        return $assignment;
    }

    public function acceptAssignment(TaskAssignment $assignment, User $actor): TaskAssignment
    {
        return DB::transaction(function () use ($assignment, $actor): TaskAssignment {
            $lockedAssignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $this->assertActorOwnsAssignment($lockedAssignment, $actor);

            if ($lockedAssignment->status !== TaskAssignmentStatus::ASSIGNED) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only assigned tasks can be accepted.',
                ]);
            }

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedAssignment->task_id);

            if ($lockedTask->status !== TaskStatus::ASSIGNED) {
                throw ValidationException::withMessages([
                    'task' => 'Only assigned tasks can be accepted.',
                ]);
            }

            $lockedAssignment->forceFill([
                'status' => TaskAssignmentStatus::ACCEPTED,
                'accepted_at' => now(),
            ])->save();

            $lockedTask->forceFill([
                'status' => TaskStatus::ACCEPTED,
                'assigned_to_user_id' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'accepted',
                'to_user_id' => $actor->id,
                'performed_by' => $actor->id,
            ]);

            $this->runAfterCommit(function () use ($lockedTask, $actor): void {
                $freshTask = $this->resolveNotificationTask($lockedTask);

                if ($freshTask) {
                    $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'accepted', $actor);
                }
            });

            return $lockedAssignment->refresh();
        });
    }

    public function rejectAssignment(TaskAssignment $assignment, User $actor, string $reason): TaskAssignment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Rejection reason is required.',
            ]);
        }

        $updatedAssignment = DB::transaction(function () use ($assignment, $actor, $reason): TaskAssignment {
            $lockedAssignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $this->assertActorOwnsAssignment($lockedAssignment, $actor);

            if (in_array($lockedAssignment->status->value, [TaskAssignmentStatus::COMPLETED->value, TaskAssignmentStatus::REJECTED->value], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'Completed or already rejected assignments cannot be rejected again.',
                ]);
            }

            $lockedAssignment->forceFill([
                'status' => TaskAssignmentStatus::REJECTED,
                'note' => $reason,
            ])->save();

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedAssignment->task_id);
            $lockedTask->forceFill([
                'assigned_to_user_id' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->taskAssignmentTargetResolver->syncTaskDispatchState($lockedTask, $actor);

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'rejected',
                'to_user_id' => $actor->id,
                'performed_by' => $actor->id,
                'note' => $reason,
            ]);

            return $lockedAssignment->refresh();
        });

        $freshTask = Task::query()->find($updatedAssignment->task_id);
        $freshAssignment = TaskAssignment::query()
            ->whereKey($updatedAssignment->id)
            ->with('assignedToUser')
            ->first();

        if ($freshTask && $freshAssignment) {
            $this->taskWorkflowNotificationService->notifyTaskRejectedToDispatchers(
                task: $freshTask,
                assignment: $freshAssignment,
                actor: $actor,
            );
            $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'rejected', $actor);
        }

        return $updatedAssignment;
    }

    public function startTask(TaskAssignment $assignment, User $actor): TaskAssignment
    {
        $completedAssignment = DB::transaction(function () use ($assignment, $actor): TaskAssignment {
            $lockedAssignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $this->assertActorOwnsAssignment($lockedAssignment, $actor);

            if (in_array($lockedAssignment->status->value, [TaskAssignmentStatus::COMPLETED->value, TaskAssignmentStatus::REJECTED->value], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only active assignments can be started.',
                ]);
            }

            if ($lockedAssignment->status !== TaskAssignmentStatus::ACCEPTED) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only accepted assignments can be started.',
                ]);
            }

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedAssignment->task_id);

            if (! in_array($lockedTask->status, [TaskStatus::ACCEPTED, TaskStatus::WAIT_RESPONSE, TaskStatus::REOPENED], true)) {
                throw ValidationException::withMessages([
                    'task' => 'Only accepted or waiting-response tasks can be started.',
                ]);
            }

            $lockedTask->forceFill([
                'status' => TaskStatus::IN_PROGRESS,
                'assigned_to_user_id' => $actor->id,
                'started_at' => $lockedTask->started_at ?? now(),
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'started',
                'to_user_id' => $actor->id,
                'performed_by' => $actor->id,
            ]);

            $this->runAfterCommit(function () use ($lockedTask, $actor): void {
                $freshTask = $this->resolveNotificationTask($lockedTask);

                if ($freshTask) {
                    $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'started', $actor);
                }
            });

            return $lockedAssignment->refresh();
        });

        return $completedAssignment;
    }

    public function waitResponseTask(TaskAssignment $assignment, User $actor): TaskAssignment
    {
        return DB::transaction(function () use ($assignment, $actor): TaskAssignment {
            $lockedAssignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $this->assertActorOwnsAssignment($lockedAssignment, $actor);

            if ($lockedAssignment->status !== TaskAssignmentStatus::ACCEPTED) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only accepted assignments can be moved to waiting response.',
                ]);
            }

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedAssignment->task_id);

            if (! in_array($lockedTask->status, [TaskStatus::IN_PROGRESS, TaskStatus::REOPENED], true)) {
                throw ValidationException::withMessages([
                    'task' => 'Only in-progress tasks can be moved to waiting response.',
                ]);
            }

            $lockedTask->forceFill([
                'status' => TaskStatus::WAIT_RESPONSE,
                'assigned_to_user_id' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            return $lockedAssignment->refresh();
        });
    }

    public function resumeTask(TaskAssignment $assignment, User $actor): TaskAssignment
    {
        return DB::transaction(function () use ($assignment, $actor): TaskAssignment {
            $lockedAssignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $this->assertActorOwnsAssignment($lockedAssignment, $actor);

            if ($lockedAssignment->status !== TaskAssignmentStatus::ACCEPTED) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only accepted assignments can be resumed.',
                ]);
            }

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedAssignment->task_id);

            if ($lockedTask->status !== TaskStatus::WAIT_RESPONSE) {
                throw ValidationException::withMessages([
                    'task' => 'Only waiting-response tasks can be resumed.',
                ]);
            }

            $lockedTask->forceFill([
                'status' => TaskStatus::IN_PROGRESS,
                'assigned_to_user_id' => $actor->id,
                'started_at' => $lockedTask->started_at ?? now(),
                'updated_by' => $actor->id,
            ])->save();

            return $lockedAssignment->refresh();
        });
    }

    public function completeAssignedTask(TaskAssignment $assignment, User $actor): TaskAssignment
    {
        $completedAssignment = DB::transaction(function () use ($assignment, $actor): TaskAssignment {
            $lockedAssignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            $this->assertActorOwnsAssignment($lockedAssignment, $actor);

            if (in_array($lockedAssignment->status->value, [TaskAssignmentStatus::COMPLETED->value, TaskAssignmentStatus::REJECTED->value], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'Assignment cannot be completed in its current state.',
                ]);
            }

            if ($lockedAssignment->status !== TaskAssignmentStatus::ACCEPTED) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only accepted assignments can be completed.',
                ]);
            }

            $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedAssignment->task_id);

            if (! in_array($lockedTask->status, [TaskStatus::IN_PROGRESS, TaskStatus::WAIT_RESPONSE, TaskStatus::REOPENED], true)) {
                throw ValidationException::withMessages([
                    'task' => 'Only in-progress or waiting-response tasks can be completed.',
                ]);
            }

            $lockedAssignment->forceFill([
                'status' => TaskAssignmentStatus::COMPLETED,
                'accepted_at' => $lockedAssignment->accepted_at ?? now(),
                'completed_at' => now(),
            ])->save();

            $lockedTask->forceFill([
                'status' => TaskStatus::AWAITING_REPORTER_CONFIRMATION,
                'assigned_to_user_id' => $actor->id,
                'completed_at' => null,
                'resolution_submitted_by_user_id' => $actor->id,
                'resolution_submitted_at' => now(),
                'reporter_confirmation_status' => 'pending',
                'reporter_confirmed_by_user_id' => null,
                'reporter_confirmed_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'resolution_submitted',
                'to_user_id' => $actor->id,
                'performed_by' => $actor->id,
            ]);

            $this->addWorkflowComment($lockedTask, $actor, 'تم الحل وإرسال المهمة إلى المبلّغ للتأكيد.');

            return $lockedAssignment->refresh();
        });

        $freshTask = Task::query()
            ->with(['reportedByUser', 'whatsappContact.user'])
            ->find($completedAssignment->task_id);

        if ($freshTask) {
            if ($reporter = $this->resolveReporterUser($freshTask)) {
                $this->taskWorkflowNotificationService->notifyReporterConfirmationRequested($freshTask, $reporter);
                $this->fcmNotificationService->notifyReporterConfirmationRequested($freshTask, $reporter);
            }

            $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'resolution_submitted', $actor);
        }

        return $completedAssignment;
    }

    public function confirmResolution(Task $task, User $actor, ?string $comment = null): Task
    {
        $resolvedTask = DB::transaction(function () use ($task, $actor, $comment): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== TaskStatus::AWAITING_REPORTER_CONFIRMATION) {
                throw ValidationException::withMessages([
                    'task' => 'Task is not waiting for reporter confirmation.',
                ]);
            }

            $lockedTask->forceFill([
                'status' => TaskStatus::COMPLETED,
                'completed_at' => now(),
                'reporter_confirmation_status' => 'confirmed',
                'reporter_confirmed_by_user_id' => $actor->id,
                'reporter_confirmed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'reporter_confirmed',
                'to_user_id' => $lockedTask->assigned_to_user_id,
                'performed_by' => $actor->id,
            ]);

            $this->addWorkflowComment(
                $lockedTask,
                $actor,
                $this->composeComment(
                    prefix: 'أكد المبلّغ حل المشكلة وتم إغلاق المهمة.',
                    comment: $comment,
                ),
            );

            return $lockedTask->refresh();
        });

        $freshTask = Task::query()
            ->with(['assignedToUser', 'assignments.assignedToUser', 'assignmentTargets'])
            ->find($resolvedTask->id);

        if ($freshTask) {
            $assignees = $this->taskAccessService->resolveAssignees($freshTask);
            $assigneeIds = $assignees
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $this->taskWhatsAppNotificationService->notifyTaskCompleted($freshTask);
            $this->taskWorkflowNotificationService->notifyReporterConfirmedResolution(
                task: $freshTask,
                recipients: $assignees,
            );
            $this->taskWorkflowNotificationService->notifyTaskCompletedToDispatchers($freshTask);
            $this->fcmNotificationService->notifyReporterConfirmedResolution($freshTask, $assigneeIds);
            $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'reporter_confirmed', $actor);
        }

        return $resolvedTask;
    }

    public function rejectResolution(Task $task, User $actor, string $comment): Task
    {
        $comment = trim($comment);

        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'A reporter comment is required.',
            ]);
        }

        $reopenedTask = DB::transaction(function () use ($task, $actor, $comment): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== TaskStatus::AWAITING_REPORTER_CONFIRMATION) {
                throw ValidationException::withMessages([
                    'task' => 'Task is not waiting for reporter confirmation.',
                ]);
            }

            $reopenedAssignment = $lockedTask->assignments()
                ->where('assigned_to_user_id', $lockedTask->assigned_to_user_id)
                ->where('status', TaskAssignmentStatus::COMPLETED->value)
                ->latest('id')
                ->first()
                ?? $lockedTask->assignments()
                    ->where('status', TaskAssignmentStatus::COMPLETED->value)
                    ->latest('id')
                    ->first();

            if ($reopenedAssignment) {
                $reopenedAssignment->forceFill([
                    'status' => TaskAssignmentStatus::ACCEPTED,
                    'completed_at' => null,
                ])->save();
            }

            $lockedTask->forceFill([
                'status' => TaskStatus::REOPENED,
                'completed_at' => null,
                'reporter_confirmation_status' => 'rejected',
                'reporter_confirmed_by_user_id' => $actor->id,
                'reporter_confirmed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'reporter_rejected',
                'to_user_id' => $lockedTask->assigned_to_user_id,
                'performed_by' => $actor->id,
                'note' => $comment,
            ]);

            $this->addWorkflowComment(
                $lockedTask,
                $actor,
                $this->composeComment(
                    prefix: 'المبلّغ أكد أن المشكلة لم تُحل وأعاد المهمة للمتابعة.',
                    comment: $comment,
                ),
            );

            return $lockedTask->refresh();
        });

        $freshTask = Task::query()
            ->with(['assignedToUser', 'assignments.assignedToUser', 'assignmentTargets'])
            ->find($reopenedTask->id);

        if ($freshTask) {
            $assignees = $this->taskAccessService->resolveAssignees($freshTask);
            $assigneeIds = $assignees
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $this->taskWorkflowNotificationService->notifyReporterRejectedResolution($freshTask, $assignees);
            $this->fcmNotificationService->notifyReporterRejectedResolution($freshTask, $assigneeIds);
            $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'reporter_rejected', $actor);
        }

        return $reopenedTask;
    }

    public function reassignTask(Task $task, int $newUserId, User $actor, ?string $reason = null): TaskAssignment
    {
        $assignment = DB::transaction(function () use ($task, $newUserId, $actor, $reason): TaskAssignment {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if (in_array($lockedTask->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true)) {
                throw ValidationException::withMessages([
                    'task' => 'Completed or cancelled tasks cannot be reassigned.',
                ]);
            }

            $previousUserId = $lockedTask->assigned_to_user_id;

            // Cancel any active assignments
            $lockedTask->assignments()
                ->whereIn('status', [TaskAssignmentStatus::ASSIGNED->value, TaskAssignmentStatus::ACCEPTED->value])
                ->each(function (TaskAssignment $a) {
                    $a->forceFill(['status' => TaskAssignmentStatus::REJECTED, 'note' => 'Reassigned'])->save();
                });

            User::query()->findOrFail($newUserId);

            $assignment = $lockedTask->assignments()->create([
                'assigned_to_user_id' => $newUserId,
                'assigned_by_user_id' => $actor->id,
                'note' => $reason,
                'status' => TaskAssignmentStatus::ASSIGNED->value,
                'assigned_at' => now(),
            ]);

            $lockedTask->forceFill([
                'assigned_to_user_id' => $newUserId,
                'status' => TaskStatus::ASSIGNED->value,
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'reassigned',
                'from_user_id' => $previousUserId,
                'to_user_id' => $newUserId,
                'performed_by' => $actor->id,
                'note' => $reason,
            ]);

            return $assignment->refresh();
        });

        $freshTask = Task::query()->find($assignment->task_id);
        $freshAssignment = TaskAssignment::query()
            ->whereKey($assignment->id)
            ->with(['assignedToUser', 'assignedByUser'])
            ->first();

        if ($freshTask) {
            $this->taskWhatsAppNotificationService->notifyTaskAssigned($freshTask);

            if ($freshAssignment?->assignedToUser) {
                $this->taskWorkflowNotificationService->notifyTaskAssignedToEmployee(
                    task: $freshTask,
                    assignment: $freshAssignment,
                    context: 'reassigned',
                );
                $this->fcmNotificationService->notifyNewTaskAssigned(
                    task: $freshTask,
                    assignee: $freshAssignment->assignedToUser,
                    actor: $actor,
                    context: 'reassigned',
                );
            }
        }

        return $assignment;
    }

    private function assertActorOwnsAssignment(TaskAssignment $assignment, User $actor): void
    {
        if ($assignment->assigned_to_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'assignment' => 'You can only act on your own assignment.',
            ]);
        }
    }

    private function addWorkflowComment(Task $task, User $actor, string $comment): void
    {
        $task->comments()->create([
            'user_id' => $actor->id,
            'comment' => $comment,
            'is_internal' => false,
        ]);
    }

    private function composeComment(string $prefix, ?string $comment = null): string
    {
        $comment = trim((string) $comment);

        if ($comment === '') {
            return $prefix;
        }

        return $prefix.PHP_EOL.PHP_EOL.$comment;
    }

    private function resolveReporterUser(Task $task): ?User
    {
        $task->loadMissing(['reportedByUser', 'whatsappContact.user']);

        if ($task->reportedByUser) {
            return $task->reportedByUser;
        }

        if (filled($task->reported_by_user_id)) {
            return User::query()->find($task->reported_by_user_id);
        }

        if ($task->whatsappContact?->user) {
            return $task->whatsappContact->user;
        }

        if ($task->whatsappContact?->user_id) {
            return User::query()->find($task->whatsappContact->user_id);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $relations
     */
    private function resolveNotificationTask(Task $task, array $relations = []): ?Task
    {
        if ($this->shouldRunAfterCommitImmediately()) {
            return $task->loadMissing($relations);
        }

        return Task::query()
            ->with($relations)
            ->find($task->id);
    }

    /**
     * @param  array<int, string>  $relations
     */
    private function resolveNotificationAssignment(TaskAssignment $assignment, array $relations = []): ?TaskAssignment
    {
        if ($this->shouldRunAfterCommitImmediately()) {
            return $assignment->loadMissing($relations);
        }

        return TaskAssignment::query()
            ->whereKey($assignment->id)
            ->with($relations)
            ->first();
    }
}
