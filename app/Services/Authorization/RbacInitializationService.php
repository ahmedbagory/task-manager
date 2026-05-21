<?php

namespace App\Services\Authorization;

use App\Support\Rbac;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacInitializationService
{
    public function seed(): void
    {
        $this->resetPermissionCache();

        foreach (Rbac::PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $this->resetPermissionCache();

        foreach (Rbac::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissionNames);
        }

        $this->resetPermissionCache();
    }

    private function resetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
