<?php

namespace Tests\Feature\Filament;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\TaskAssignmentTarget;
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

    public function test_view_page_keeps_assignment_actions_visible_after_task_has_active_assignment(): void
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
            ->assertActionVisible('assign')
            ->assertActionVisible('reassign');
    }

    public function test_assign_action_updates_user_targets_and_records_single_history_entry(): void
    {
        app(RbacInitializationService::class)->seed();
        app()->setLocale('ar');

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
            ->callAction('assign', [
                'assignment_target_users' => [$employee->id],
                'note' => 'Please handle quickly.',
            ]);

        $task->refresh();

        $this->assertNull($task->assigned_to_user_id);
        $this->assertSame(TaskStatus::ASSIGNED, $task->workflowStatus());
        $this->assertDatabaseCount('task_assignment_histories', 1);
        $this->assertDatabaseHas('task_assignment_histories', [
            'task_id' => $task->id,
            'action' => 'targets_updated',
            'performed_by' => $dispatcher->id,
            'note' => 'Please handle quickly.',
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => 'user',
            'target_id' => $employee->id,
            'assigned_by' => $dispatcher->id,
        ]);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('بانتظار قبول أحد الموظفين')
            ->assertDontSee('بانتظار الإسناد');
    }

    public function test_reassign_action_resets_active_assignment_and_records_single_reassignment_history_entry(): void
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
                'assignment_target_users' => [$employeeTwo->id],
                'reason' => 'Needs a different specialist.',
            ]);

        $task->refresh();

        $this->assertNull($task->assigned_to_user_id);
        $this->assertSame(TaskAssignmentStatus::REJECTED, $task->assignments()->latest('id')->first()->status);
        $this->assertSame(2, TaskAssignmentHistory::query()->count());
        $this->assertSame(1, TaskAssignmentHistory::query()->where('action', 'assigned')->count());
        $this->assertSame(1, TaskAssignmentHistory::query()->where('action', 'reassigned')->count());
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => 'user',
            'target_id' => $employeeTwo->id,
            'assigned_by' => $dispatcher->id,
        ]);
    }

    public function test_assign_action_can_target_all_eligible_users(): void
    {
        app(RbacInitializationService::class)->seed();
        app()->setLocale('ar');

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employeeOne = User::factory()->create();
        $employeeOne->assignRole(Rbac::EMPLOYEE);

        $employeeTwo = User::factory()->create();
        $employeeTwo->assignRole(Rbac::EMPLOYEE);

        $supervisor = User::factory()->create();
        $supervisor->assignRole(Rbac::SUPERVISOR);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('assign', [
                'assign_to_all' => true,
            ]);

        $this->assertSame(3, $task->fresh()->assignmentTargets()->count());
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => User::class,
            'target_id' => $employeeOne->id,
            'assigned_by' => $dispatcher->id,
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => User::class,
            'target_id' => $employeeTwo->id,
            'assigned_by' => $dispatcher->id,
        ]);
        $this->assertDatabaseHas('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => User::class,
            'target_id' => $supervisor->id,
            'assigned_by' => $dispatcher->id,
        ]);
        $this->assertDatabaseMissing('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => TaskAssignmentTarget::LEGACY_ALL,
        ]);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('بانتظار قبول أحد الموظفين')
            ->assertDontSee('بانتظار الإسناد')
            ->assertSee($employeeOne->name)
            ->assertSee($employeeTwo->name)
            ->assertSee($supervisor->name);
    }

    public function test_task_without_targets_shows_unassigned_state(): void
    {
        app(RbacInitializationService::class)->seed();
        app()->setLocale('ar');

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $task = Task::factory()->create([
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('غير مسندة')
            ->assertSee('بانتظار الإسناد')
            ->assertDontSee('بانتظار قبول أحد الموظفين');
    }

    public function test_view_page_does_not_crash_when_legacy_all_target_rows_exist(): void
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

        $this->actingAs($dispatcher);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('الأسماء المستهدفة')
            ->assertSee($employee->name)
            ->assertSee($supervisor->name);
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
