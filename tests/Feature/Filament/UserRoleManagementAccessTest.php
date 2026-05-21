<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_user_and_role_management(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Role::class));
    }

    public function test_employee_cannot_access_user_or_role_management(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($employee)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', Role::class));
        $this->assertFalse(Gate::forUser($employee)->allows('create', Role::class));
    }
}
