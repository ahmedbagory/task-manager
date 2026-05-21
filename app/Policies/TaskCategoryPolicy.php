<?php

namespace App\Policies;

use App\Models\TaskCategory;
use App\Models\User;

class TaskCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, TaskCategory $taskCategory): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->can('task_categories.manage');
    }

    public function update(User $user, TaskCategory $taskCategory): bool
    {
        return $user->can('task_categories.manage');
    }

    public function delete(User $user, TaskCategory $taskCategory): bool
    {
        return $user->can('task_categories.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('task_categories.manage');
    }

    private function canView(User $user): bool
    {
        return $user->can('task_categories.view') || $user->can('task_categories.manage');
    }
}
