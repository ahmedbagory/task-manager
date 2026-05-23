<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Eloquent\Builder;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        if ($user->hasRole(Rbac::EMPLOYEE)) {
            return $this->isOwnTask($user, $task);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create') && (! $user->hasRole(Rbac::EMPLOYEE));
    }

    public function update(User $user, Task $task): bool
    {
        if ($this->canManageAllTasks($user)) {
            return true;
        }

        if ($user->can('tasks.update_status')) {
            return $this->isOwnTask($user, $task);
        }

        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->canHardManageTasks($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canHardManageTasks($user);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->canHardManageTasks($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canHardManageTasks($user);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->canHardManageTasks($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->canHardManageTasks($user);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can('tasks.assign') && $this->view($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->can('tasks.comment') && $this->view($user, $task);
    }

    public function viewAssignedWorkspace(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        return $task->assigned_to_user_id === $user->id
            || $this->isTargetedUser($user, $task);
    }

    public function respondToAssignment(User $user, Task $task): bool
    {
        return $this->viewAssignedWorkspace($user, $task);
    }

    public function addWorkspaceComment(User $user, Task $task): bool
    {
        return $user->can('tasks.comment')
            && $this->viewAssignedWorkspace($user, $task);
    }

    public function uploadWorkspaceAttachment(User $user, Task $task): bool
    {
        return $user->can('tasks.attachments.view')
            && $this->viewAssignedWorkspace($user, $task);
    }

    private function isTargetedUser(User $user, Task $task): bool
    {
        if (! $task->assignmentTargets()->exists()) {
            return false;
        }

        return $task->assignmentTargets()
            ->where(function (Builder $query) use ($user): void {
                $query->where('target_type', 'all')
                    ->orWhere(function (Builder $q) use ($user): void {
                        $q->where('target_type', 'user')->where('target_id', $user->id);
                    });

                $departmentIds = array_filter([$user->department_id, $user->department?->parent_id]);

                if ($departmentIds !== []) {
                    $query->orWhere(function (Builder $q) use ($departmentIds): void {
                        $q->where('target_type', 'department')->whereIn('target_id', $departmentIds);
                    });
                }
            })
            ->exists();
    }

    public function viewAssignments(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function viewAttachments(User $user, Task $task): bool
    {
        return $user->can('tasks.attachments.view') && $this->view($user, $task);
    }

    private function canManageAllTasks(User $user): bool
    {
        return $user->can('tasks.manage');
    }

    private function canHardManageTasks(User $user): bool
    {
        return $user->hasAnyRole([Rbac::SUPER_ADMIN, Rbac::ADMIN]);
    }

    private function isOwnTask(User $user, Task $task): bool
    {
        return $task->assigned_to_user_id === $user->id
            || $task->reported_by_user_id === $user->id
            || $task->created_by === $user->id;
    }
}
