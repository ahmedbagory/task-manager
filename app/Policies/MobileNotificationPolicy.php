<?php

namespace App\Policies;

use App\Models\MobileNotification;
use App\Models\User;

class MobileNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('mobile_notifications.view');
    }

    public function view(User $user, MobileNotification $mobileNotification): bool
    {
        return $user->can('mobile_notifications.view');
    }

    public function create(User $user): bool
    {
        return $user->can('mobile_notifications.send');
    }

    public function update(User $user, MobileNotification $mobileNotification): bool
    {
        return false;
    }

    public function delete(User $user, MobileNotification $mobileNotification): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
