<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.manage');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.manage') && $user->id !== $model->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    private function canView(User $user): bool
    {
        return $user->can('users.view') || $user->can('users.manage');
    }
}
