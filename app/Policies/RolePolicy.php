<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    private function canView(User $user): bool
    {
        return $user->can('roles.view') || $user->can('roles.manage');
    }
}
