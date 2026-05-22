<?php

namespace Tests\Feature\Filament;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Tasks\TaskAssignmentService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskViewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_page_hides_assign_action_after_task_has_active_assignment(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Handle this task.');

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertActionHidden('assignEmployee')
            ->assertActionVisible('reassign');
    }

    public function test_assign_employee_action_uses_service_without_duplicate_history_entries(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('assignEmployee', [
                'assigned_to_user_id' => $employee->id,
                'note' => 'Please handle quickly.',
            ]);

        $this->assertSame($employee->id, $task->fresh()->assigned_to_user_id);
        $this->assertDatabaseCount('task_assignment_histories', 1);
        $this->assertDatabaseHas('task_assignment_histories', [
            'task_id' => $task->id,
            'action' => 'assigned',
            'to_user_id' => $employee->id,
        ]);
    }

    public function test_reassign_action_creates_single_reassignment_history_entry(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employeeOne = User::factory()->create();
        $employeeOne->assignRole(Rbac::EMPLOYEE);

        $employeeTwo = User::factory()->create();
        $employeeTwo->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        app(TaskAssignmentService::class)->assignTask($task, $employeeOne->id, $dispatcher, 'Initial assignment.');

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('reassign', [
                'new_user_id' => $employeeTwo->id,
                'reason' => 'Needs a different specialist.',
            ]);

        $task->refresh();

        $this->assertSame($employeeTwo->id, $task->assigned_to_user_id);
        $this->assertSame(2, TaskAssignmentHistory::query()->count());
        $this->assertSame(1, TaskAssignmentHistory::query()->where('action', 'assigned')->count());
        $this->assertSame(1, TaskAssignmentHistory::query()->where('action', 'reassigned')->count());
    }

    public function test_assign_targets_action_stores_selected_user_targets(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('assignTargets', [
                'assignment_target_users' => [$employee->id],
            ]);

        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => 'user',
            'target_id' => $employee->id,
            'assigned_by' => $dispatcher->id,
        ]);
    }

    public function test_dispatcher_can_preview_task_attachment_from_admin_context(): void
    {
        app(RbacInitializationService::class)->seed();

        Storage::fake('local');

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $path = "task-attachments/{$task->id}/preview.pdf";
        Storage::disk('local')->put($path, 'preview');

        $attachment = TaskAttachment::query()->create([
            'task_id' => $task->id,
            'user_id' => $employee->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'preview.pdf',
            'mime_type' => 'application/pdf',
            'size' => 7,
            'type' => 'file',
        ]);

        $this->actingAs($dispatcher)
            ->get(route('attachments.preview', $attachment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
