<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->can('departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('departments.manage');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('departments.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('departments.manage');
    }

    private function canView(User $user): bool
    {
        return $user->can('departments.view') || $user->can('departments.manage');
    }
}
