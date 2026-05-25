<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;
use App\Support\Rbac;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view') && ! $this->taskAccessService()->isReporter($user, $task)) {
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
        if ($this->taskAccessService()->isReporter($user, $task)) {
            return true;
        }

        if (! $user->can('tasks.view')) {
            return false;
        }

        return $this->taskAccessService()->isParticipant($user, $task)
            || $task->created_by === $user->id;
    }

    public function respondToAssignment(User $user, Task $task): bool
    {
        $role = $this->taskAccessService()->resolveCurrentUserRole($task, $user);

        return $user->can('tasks.view')
            && in_array($role, ['assignee', 'assignee_and_requester'], true);
    }

    public function addWorkspaceComment(User $user, Task $task): bool
    {
        if ($this->taskAccessService()->isReporter($user, $task)) {
            return true;
        }

        return $user->can('tasks.comment') && $this->viewAssignedWorkspace($user, $task);
    }

    public function uploadWorkspaceAttachment(User $user, Task $task): bool
    {
        if ($this->taskAccessService()->isReporter($user, $task)) {
            return true;
        }

        return $user->can('tasks.attachments.view') && $this->viewAssignedWorkspace($user, $task);
    }

    public function viewAssignments(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function viewAttachments(User $user, Task $task): bool
    {
        return $this->uploadWorkspaceAttachment($user, $task);
    }

    public function confirmResolution(User $user, Task $task): bool
    {
        return $this->taskAccessService()->resolveAllowedActions($task, $user)['can_confirm_resolution'];
    }

    public function rejectResolution(User $user, Task $task): bool
    {
        return $this->taskAccessService()->resolveAllowedActions($task, $user)['can_reject_resolution'];
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
        return $this->taskAccessService()->isAssignee($user, $task)
            || $this->taskAccessService()->isReporter($user, $task)
            || $task->reported_by_user_id === $user->id
            || $task->created_by === $user->id;
    }

    private function taskAccessService(): TaskAccessService
    {
        return app(TaskAccessService::class);
    }
}
