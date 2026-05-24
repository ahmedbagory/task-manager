<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Rbac;

class WhatsappMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, WhatsappMessage $whatsappMessage): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WhatsappMessage $whatsappMessage): bool
    {
        return false;
    }

    public function delete(User $user, WhatsappMessage $whatsappMessage): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function convertToTask(User $user, WhatsappMessage $whatsappMessage): bool
    {
        return $user->can('inbox.convert_to_task')
            && $user->hasAnyRole([Rbac::SUPER_ADMIN, Rbac::ADMIN, Rbac::DISPATCHER]);
    }

    public function send(User $user): bool
    {
        return $this->canView($user)
            && $user->hasAnyRole([Rbac::SUPER_ADMIN, Rbac::ADMIN, Rbac::DISPATCHER]);
    }

    public function retry(User $user, WhatsappMessage $whatsappMessage): bool
    {
        return $this->send($user);
    }

    private function canView(User $user): bool
    {
        return $user->can('whatsapp_messages.view') || $user->can('inbox.view');
    }
}
