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
use App\Services\WhatsApp\TaskWhatsAppNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskAssignmentService
{
    public function __construct(
        private readonly TaskWhatsAppNotificationService $taskWhatsAppNotificationService,
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
        private readonly FcmNotificationService $fcmNotificationService,
    ) {}

    public function assignTask(Task $task, int $assignedToUserId, ?User $assignedBy = null, ?string $note = null): TaskAssignment
    {
        return DB::transaction(function () use ($task, $assignedToUserId, $assignedBy, $note): TaskAssignment {
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

            DB::afterCommit(function () use ($lockedTask, $assignment): void {
                $freshTask = Task::query()->find($lockedTask->id);
                $freshAssignment = TaskAssignment::query()
                    ->whereKey($assignment->id)
                    ->with('assignedToUser')
                    ->first();

                if ($freshTask) {
                    $this->taskWhatsAppNotificationService->notifyTaskAssigned($freshTask);

                    if ($freshAssignment) {
                        $this->taskWorkflowNotificationService->notifyTaskAssignedToEmployee(
                            task: $freshTask,
                            assignment: $freshAssignment,
                        );

                        if ($freshAssignment->assignedToUser) {
                            $this->fcmNotificationService->notifyNewTaskAssigned(
                                task: $freshTask,
                                assignee: $freshAssignment->assignedToUser,
                            );
                        }
                    }
                }
            });

            return $assignment->refresh();
        });
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

            DB::afterCommit(function () use ($lockedTask, $actor): void {
                $freshTask = Task::query()->find($lockedTask->id);

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

        return DB::transaction(function () use ($assignment, $actor, $reason): TaskAssignment {
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
                'status' => TaskStatus::REJECTED,
                'assigned_to_user_id' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'rejected',
                'to_user_id' => $actor->id,
                'performed_by' => $actor->id,
                'note' => $reason,
            ]);

            DB::afterCommit(function () use ($lockedTask, $lockedAssignment, $actor): void {
                $freshTask = Task::query()->find($lockedTask->id);
                $freshAssignment = TaskAssignment::query()
                    ->whereKey($lockedAssignment->id)
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
            });

            return $lockedAssignment->refresh();
        });
    }

    public function startTask(TaskAssignment $assignment, User $actor): TaskAssignment
    {
        return DB::transaction(function () use ($assignment, $actor): TaskAssignment {
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

            if (! in_array($lockedTask->status, [TaskStatus::ACCEPTED, TaskStatus::WAIT_RESPONSE], true)) {
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

            DB::afterCommit(function () use ($lockedTask, $actor): void {
                $freshTask = Task::query()->find($lockedTask->id);

                if ($freshTask) {
                    $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'started', $actor);
                }
            });

            return $lockedAssignment->refresh();
        });
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

            if ($lockedTask->status !== TaskStatus::IN_PROGRESS) {
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
        return DB::transaction(function () use ($assignment, $actor): TaskAssignment {
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

            if (! in_array($lockedTask->status, [TaskStatus::IN_PROGRESS, TaskStatus::WAIT_RESPONSE], true)) {
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
                'status' => TaskStatus::COMPLETED,
                'assigned_to_user_id' => $actor->id,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            TaskAssignmentHistory::query()->create([
                'task_id' => $lockedTask->id,
                'action' => 'completed',
                'to_user_id' => $actor->id,
                'performed_by' => $actor->id,
            ]);

            DB::afterCommit(function () use ($lockedTask, $lockedAssignment, $actor): void {
                $freshTask = Task::query()->find($lockedTask->id);
                $freshAssignment = TaskAssignment::query()
                    ->whereKey($lockedAssignment->id)
                    ->with('assignedToUser')
                    ->first();

                if ($freshTask) {
                    $this->taskWhatsAppNotificationService->notifyTaskCompleted($freshTask);
                    $this->taskWorkflowNotificationService->notifyTaskCompletedToDispatchers(
                        task: $freshTask,
                        assignment: $freshAssignment,
                    );
                    $this->fcmNotificationService->notifyDispatchersTaskUpdate($freshTask, 'completed', $actor);
                }
            });

            return $lockedAssignment->refresh();
        });
    }

    public function reassignTask(Task $task, int $newUserId, User $actor, ?string $reason = null): TaskAssignment
    {
        return DB::transaction(function () use ($task, $newUserId, $actor, $reason): TaskAssignment {
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

            DB::afterCommit(function () use ($lockedTask, $assignment): void {
                $freshTask = Task::query()->find($lockedTask->id);
                $freshAssignment = TaskAssignment::query()
                    ->whereKey($assignment->id)
                    ->with('assignedToUser')
                    ->first();

                if ($freshTask) {
                    $this->taskWhatsAppNotificationService->notifyTaskAssigned($freshTask);

                    if ($freshAssignment?->assignedToUser) {
                        $this->fcmNotificationService->notifyNewTaskAssigned(
                            task: $freshTask,
                            assignee: $freshAssignment->assignedToUser,
                        );
                    }
                }
            });

            return $assignment->refresh();
        });
    }

    private function assertActorOwnsAssignment(TaskAssignment $assignment, User $actor): void
    {
        if ($assignment->assigned_to_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'assignment' => 'You can only act on your own assignment.',
            ]);
        }
    }
}
