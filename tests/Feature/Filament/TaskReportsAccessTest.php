<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReportsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_task_reports_page(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->actingAs($dispatcher)
            ->get(route('filament.admin.resources.task-reports.index'))
            ->assertOk();
    }

    public function test_employee_cannot_access_task_reports_page(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $this->actingAs($employee)
            ->get(route('filament.admin.resources.task-reports.index'))
            ->assertForbidden();
    }
}
