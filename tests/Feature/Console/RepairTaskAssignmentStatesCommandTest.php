<?php

namespace Tests\Feature\Console;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairTaskAssignmentStatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_repairs_inconsistent_assignment_states_and_converts_legacy_rows(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $supervisor = User::factory()->create();
        $supervisor->assignRole(Rbac::SUPERVISOR);

        $pendingAssignmentWithTargets = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        TaskAssignmentTarget::query()->create([
            'task_id' => $pendingAssignmentWithTargets->id,
            'target_type' => 'user',
            'target_id' => $employee->id,
            'assigned_by' => $dispatcher->id,
        ]);

        $assignedWithoutAudience = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $legacyAllTask = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        TaskAssignmentTarget::query()->create([
            'task_id' => $legacyAllTask->id,
            'target_type' => TaskAssignmentTarget::LEGACY_ALL,
            'target_id' => 0,
            'assigned_by' => $dispatcher->id,
        ]);

        $this->artisan('tasks:repair-assignment-states')
            ->assertExitCode(0);

        $this->assertSame(TaskStatus::ASSIGNED, $pendingAssignmentWithTargets->fresh()->status);
        $this->assertSame(TaskStatus::PENDING_ASSIGNMENT, $assignedWithoutAudience->fresh()->status);
        $this->assertSame(TaskStatus::ASSIGNED, $legacyAllTask->fresh()->status);

        $this->assertDatabaseMissing('task_assignment_targets', [
            'task_id' => $legacyAllTask->id,
            'target_type' => TaskAssignmentTarget::LEGACY_ALL,
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $legacyAllTask->id,
            'target_type' => User::class,
            'target_id' => $employee->id,
            'assigned_by' => $dispatcher->id,
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $legacyAllTask->id,
            'target_type' => User::class,
            'target_id' => $supervisor->id,
            'assigned_by' => $dispatcher->id,
        ]);
    }
}
