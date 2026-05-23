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

class CleanupLegacyAllTaskAssignmentTargetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_converts_legacy_all_targets_into_concrete_user_rows(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $supervisor = User::factory()->create();
        $supervisor->assignRole(Rbac::SUPERVISOR);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        TaskAssignmentTarget::query()->create([
            'task_id' => $task->id,
            'target_type' => TaskAssignmentTarget::LEGACY_ALL,
            'target_id' => 0,
            'assigned_by' => $dispatcher->id,
        ]);

        $this->artisan('tasks:cleanup-legacy-all-targets')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => TaskAssignmentTarget::LEGACY_ALL,
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => User::class,
            'target_id' => $employee->id,
            'assigned_by' => $dispatcher->id,
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => User::class,
            'target_id' => $supervisor->id,
            'assigned_by' => $dispatcher->id,
        ]);
    }
}
