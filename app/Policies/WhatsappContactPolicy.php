<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappContact;

class WhatsappContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, WhatsappContact $whatsappContact): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->can('whatsapp_contacts.manage');
    }

    public function update(User $user, WhatsappContact $whatsappContact): bool
    {
        return $user->can('whatsapp_contacts.manage');
    }

    public function delete(User $user, WhatsappContact $whatsappContact): bool
    {
        return $user->can('whatsapp_contacts.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('whatsapp_contacts.manage');
    }

    private function canView(User $user): bool
    {
        return $user->can('whatsapp_contacts.view') || $user->can('whatsapp_contacts.manage');
    }
}
