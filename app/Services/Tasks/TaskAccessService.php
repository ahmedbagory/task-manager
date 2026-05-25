<?php

namespace App\Services\Tasks;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskStatus;
use App\Http\Resources\Api\UserResource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskAccessService
{
    public function __construct(
        private readonly TaskAssignmentTargetResolver $taskAssignmentTargetResolver,
    ) {}

    public function applyVisibleToUserScope(Builder $query, User $user, bool $includeCreatedBy = true): Builder
    {
        if ($this->isManagementUser($user)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user, $includeCreatedBy): void {
            $builder
                ->where('assigned_to_user_id', $user->id)
                ->orWhere('reported_by_user_id', $user->id)
                ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($user): void {
                    $assignmentQuery->where('assigned_to_user_id', $user->id);
                })
                ->orWhere(function (Builder $targetQuery) use ($user): void {
                    $this->applyTargetScope($targetQuery, $user);
                })
                ->orWhereHas('whatsappContact', function (Builder $contactQuery) use ($user): void {
                    $contactQuery->where('user_id', $user->id);
                });

            if ($includeCreatedBy) {
                $builder->orWhere('created_by', $user->id);
            }
        });
    }

    public function applyTargetScope(Builder $query, User $user): void
    {
        $query->whereHas('assignmentTargets', function (Builder $targetQuery) use ($user): void {
            $targetQuery->where('target_type', TaskAssignmentTarget::LEGACY_ALL)
                ->orWhere(function (Builder $q) use ($user): void {
                    $q->whereIn('target_type', TaskAssignmentTarget::userTargetTypes())
                        ->where('target_id', $user->id);
                });

            $departmentIds = array_filter([$user->department_id, $user->department?->parent_id]);

            if ($departmentIds !== []) {
                $targetQuery->orWhere(function (Builder $q) use ($departmentIds): void {
                    $q->whereIn('target_type', TaskAssignmentTarget::departmentTargetTypes())
                        ->whereIn('target_id', $departmentIds);
                });
            }
        });
    }

    public function isManagementUser(User $user): bool
    {
        return $user->hasAnyRole([
            Rbac::SUPER_ADMIN,
            Rbac::ADMIN,
            Rbac::DISPATCHER,
            Rbac::SUPERVISOR,
        ]) || $user->can('tasks.manage') || $user->can('tasks.assign') || $user->can('tasks.reassign');
    }

    public function isReporter(User $user, Task $task): bool
    {
        if ($task->reported_by_user_id === $user->id) {
            return true;
        }

        $task->loadMissing('whatsappContact');

        return $task->whatsappContact?->user_id === $user->id;
    }

    public function isAssignee(User $user, Task $task): bool
    {
        if ($task->assigned_to_user_id === $user->id) {
            return true;
        }

        if ($this->resolveUserAssignment($task, $user) !== null) {
            return true;
        }

        return $this->resolveAssignees($task)->contains(fn (User $assignee): bool => $assignee->id === $user->id);
    }

    public function isParticipant(User $user, Task $task): bool
    {
        return $this->isManagementUser($user)
            || $this->isReporter($user, $task)
            || $this->isAssignee($user, $task);
    }

    public function resolveUserAssignment(Task $task, User $user): ?TaskAssignment
    {
        if ($task->relationLoaded('assignments')) {
            return $task->assignments
                ->where('assigned_to_user_id', $user->id)
                ->filter(fn (TaskAssignment $assignment): bool => in_array(
                    $assignment->status->value,
                    [TaskAssignmentStatus::ASSIGNED->value, TaskAssignmentStatus::ACCEPTED->value],
                    true,
                ))
                ->sortByDesc('id')
                ->first();
        }

        return $task->assignments()
            ->where('assigned_to_user_id', $user->id)
            ->whereIn('status', [TaskAssignmentStatus::ASSIGNED->value, TaskAssignmentStatus::ACCEPTED->value])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveAssignees(Task $task): Collection
    {
        $users = collect();

        if ($task->assignedToUser) {
            $users->push($task->assignedToUser);
        }

        $task->loadMissing([
            'assignments.assignedToUser.department.parent',
            'assignmentTargets',
        ]);

        $assignmentUsers = $task->assignments
            ->filter(fn (TaskAssignment $assignment): bool => $assignment->status !== TaskAssignmentStatus::REJECTED)
            ->map(fn (TaskAssignment $assignment): ?User => $assignment->assignedToUser)
            ->filter();

        $users = $users->concat($assignmentUsers);

        if ($task->assignmentTargets->isNotEmpty()) {
            $users = $users->concat($this->taskAssignmentTargetResolver->resolveUsersForTask($task));
        }

        return $users
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveReporter(Task $task): ?array
    {
        $task->loadMissing([
            'reportedByUser.roles',
            'whatsappContact.user.roles',
        ]);

        if ($task->reportedByUser) {
            return [
                'type' => 'user',
                'name' => $task->reportedByUser->name,
                'phone' => $task->reportedByUser->phone ?: $task->reported_by_phone,
                'user' => (new UserResource($task->reportedByUser))->resolve(),
                'whatsapp_contact' => $task->whatsappContact ? [
                    'id' => $task->whatsappContact->id,
                    'name' => $task->whatsappContact->name,
                    'phone' => $task->whatsappContact->phone,
                ] : null,
            ];
        }

        if ($task->whatsappContact) {
            return [
                'type' => 'whatsapp_contact',
                'name' => $task->whatsappContact->name ?: ($task->reported_by_phone ?: $task->whatsappContact->phone),
                'phone' => $task->whatsappContact->phone ?: $task->reported_by_phone,
                'user' => $task->whatsappContact->user
                    ? (new UserResource($task->whatsappContact->user))->resolve()
                    : null,
                'whatsapp_contact' => [
                    'id' => $task->whatsappContact->id,
                    'name' => $task->whatsappContact->name,
                    'phone' => $task->whatsappContact->phone,
                ],
            ];
        }

        if (filled($task->reported_by_phone)) {
            return [
                'type' => 'phone',
                'name' => $task->reported_by_phone,
                'phone' => $task->reported_by_phone,
                'user' => null,
                'whatsapp_contact' => null,
            ];
        }

        return null;
    }

    public function resolveCurrentUserRole(Task $task, ?User $user): string
    {
        if (! $user) {
            return 'viewer';
        }

        if ($this->isManagementUser($user)) {
            return 'admin';
        }

        $isReporter = $this->isReporter($user, $task);
        $isAssignee = $this->isAssignee($user, $task);

        if ($isReporter && $isAssignee) {
            return 'assignee_and_requester';
        }

        if ($isAssignee) {
            return 'assignee';
        }

        if ($isReporter) {
            return 'requester';
        }

        if ($task->created_by === $user->id) {
            return 'creator';
        }

        return 'viewer';
    }

    /**
     * @return array<string, bool>
     */
    public function resolveAllowedActions(Task $task, ?User $user): array
    {
        if (! $user) {
            return $this->blankAllowedActions();
        }

        $status = $task->workflowStatus();
        $role = $this->resolveCurrentUserRole($task, $user);
        $assignment = $this->resolveUserAssignment($task, $user);

        $hasAssigneeRole = in_array($role, ['assignee', 'assignee_and_requester', 'admin'], true);
        $hasRequesterRole = in_array($role, ['requester', 'assignee_and_requester', 'admin'], true);
        $canCollaborate = in_array($role, ['admin', 'assignee', 'requester', 'assignee_and_requester', 'creator'], true);

        $canReporterConfirm = $status === TaskStatus::AWAITING_REPORTER_CONFIRMATION && $hasRequesterRole;

        return [
            'can_comment' => $canCollaborate,
            'can_upload' => $canCollaborate,
            'can_mark_resolved' => $hasAssigneeRole
                && $assignment !== null
                && $assignment->status === TaskAssignmentStatus::ACCEPTED
                && in_array($status, [TaskStatus::IN_PROGRESS, TaskStatus::WAIT_RESPONSE, TaskStatus::REOPENED], true),
            'can_confirm_resolution' => $canReporterConfirm,
            'can_reject_resolution' => $canReporterConfirm,
            'can_reassign' => $role === 'admin' && ($user->can('assign', $task) || $user->can('tasks.reassign')),
            'can_close' => $role === 'admin' && ($user->can('tasks.complete') || $user->can('tasks.manage')),
            'can_edit' => $user->can('update', $task),
            'can_delete' => $user->can('delete', $task),
            'can_accept' => $this->canAcceptTask($task, $user),
            'can_start' => $hasAssigneeRole
                && $assignment?->status === TaskAssignmentStatus::ACCEPTED
                && in_array($status, [TaskStatus::ACCEPTED, TaskStatus::REOPENED], true),
            'can_wait_response' => $hasAssigneeRole
                && $assignment?->status === TaskAssignmentStatus::ACCEPTED
                && in_array($status, [TaskStatus::IN_PROGRESS, TaskStatus::REOPENED], true),
            'can_resume' => $hasAssigneeRole
                && $assignment?->status === TaskAssignmentStatus::ACCEPTED
                && $status === TaskStatus::WAIT_RESPONSE,
            'can_reject_assignment' => $hasAssigneeRole
                && ($assignment !== null || $this->canAcceptTask($task, $user)),
        ];
    }

    /**
     * @return array{status: string, is_direct: bool, can_accept: bool}|null
     */
    public function resolveMyAssignment(Task $task, ?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $assignment = $this->resolveUserAssignment($task, $user);
        $workflowStatus = $task->workflowStatus();

        if ($assignment) {
            return [
                'status' => $assignment->status->value,
                'is_direct' => $task->assigned_to_user_id === $user->id,
                'can_accept' => $assignment->status === TaskAssignmentStatus::ASSIGNED,
            ];
        }

        if ($this->canAcceptTask($task, $user)) {
            return [
                'status' => $workflowStatus->value,
                'is_direct' => false,
                'can_accept' => true,
            ];
        }

        return [
            'status' => $workflowStatus->value,
            'is_direct' => false,
            'can_accept' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveResolutionState(Task $task): array
    {
        $task->loadMissing([
            'resolutionSubmittedByUser.roles',
            'reporterConfirmedByUser.roles',
        ]);

        $status = match ($task->reporter_confirmation_status) {
            'confirmed' => 'confirmed',
            'rejected' => 'rejected',
            default => ($task->workflowStatus() === TaskStatus::AWAITING_REPORTER_CONFIRMATION
                ? TaskStatus::AWAITING_REPORTER_CONFIRMATION->value
                : 'none'),
        };

        return [
            'status' => $status,
            'submitted_at' => $task->resolution_submitted_at?->toIso8601String(),
            'submitted_by' => $task->resolutionSubmittedByUser
                ? (new UserResource($task->resolutionSubmittedByUser))->resolve()
                : null,
            'confirmed_at' => $task->reporter_confirmed_at?->toIso8601String(),
            'confirmed_by' => $task->reporterConfirmedByUser
                ? (new UserResource($task->reporterConfirmedByUser))->resolve()
                : null,
        ];
    }

    private function canAcceptTask(Task $task, User $user): bool
    {
        if ($this->resolveUserAssignment($task, $user)?->status === TaskAssignmentStatus::ASSIGNED) {
            return true;
        }

        return $this->isAssignee($user, $task)
            && $task->assigned_to_user_id === null
            && in_array($task->workflowStatus(), [
                TaskStatus::NEW,
                TaskStatus::PENDING_ASSIGNMENT,
                TaskStatus::ASSIGNED,
            ], true);
    }

    /**
     * @return array<string, bool>
     */
    private function blankAllowedActions(): array
    {
        return [
            'can_comment' => false,
            'can_upload' => false,
            'can_mark_resolved' => false,
            'can_confirm_resolution' => false,
            'can_reject_resolution' => false,
            'can_reassign' => false,
            'can_close' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_accept' => false,
            'can_start' => false,
            'can_wait_response' => false,
            'can_resume' => false,
            'can_reject_assignment' => false,
        ];
    }
}
