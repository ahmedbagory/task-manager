<?php

namespace Tests\Feature\MyTasks;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Tasks\TaskAssignmentService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyTaskWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_only_tasks_assigned_to_authenticated_employee(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $otherEmployee = User::factory()->create();
        $otherEmployee->assignRole(Rbac::EMPLOYEE);

        $myTask = Task::factory()->create([
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $otherTask = Task::factory()->create([
            'assigned_to_user_id' => $otherEmployee->id,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($employee)
            ->get(route('my-tasks.index'))
            ->assertOk()
            ->assertSee($myTask->task_number)
            ->assertDontSee($otherTask->task_number);
    }

    public function test_employee_cannot_open_task_assigned_to_another_employee(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $otherEmployee = User::factory()->create();
        $otherEmployee->assignRole(Rbac::EMPLOYEE);

        $otherTask = Task::factory()->create([
            'assigned_to_user_id' => $otherEmployee->id,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($employee)
            ->get(route('my-tasks.show', $otherTask))
            ->assertForbidden();
    }

    public function test_employee_can_accept_start_and_complete_own_assignment(): void
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

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Please handle this issue.');

        $this->actingAs($employee)
            ->post(route('my-tasks.accept', $task))
            ->assertRedirect();

        $this->assertSame(TaskAssignmentStatus::ACCEPTED, $task->fresh()->assignments()->latest('id')->first()->status);

        $this->actingAs($employee)
            ->post(route('my-tasks.start', $task))
            ->assertRedirect();

        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);

        $this->actingAs($employee)
            ->post(route('my-tasks.complete', $task))
            ->assertRedirect();

        $this->assertSame(TaskStatus::AWAITING_REPORTER_CONFIRMATION, $task->fresh()->status);
        $this->assertSame(TaskAssignmentStatus::COMPLETED, $task->fresh()->assignments()->latest('id')->first()->status);
    }

    public function test_employee_can_reject_task_with_reason_and_task_returns_to_pending_assignment(): void
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

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Handle urgent issue.');

        $this->actingAs($employee)
            ->post(route('my-tasks.reject', $task), ['reason' => 'Need electrical team to handle this.'])
            ->assertRedirect(route('my-tasks.index'));

        $task->refresh();

        $this->assertSame(TaskStatus::PENDING_ASSIGNMENT, $task->status);
        $this->assertNull($task->assigned_to_user_id);
        $this->assertSame(TaskAssignmentStatus::REJECTED, $task->assignments()->latest('id')->first()->status);
    }

    public function test_employee_can_add_comment_and_upload_attachment_to_own_task(): void
    {
        app(RbacInitializationService::class)->seed();

        Storage::fake('local');

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->actingAs($employee)
            ->post(route('my-tasks.comments.store', $task), [
                'comment' => 'I have started diagnosing the problem.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $employee->id,
            'comment' => 'I have started diagnosing the problem.',
        ]);

        $upload = UploadedFile::fake()->image('evidence.jpg');

        $this->actingAs($employee)
            ->post(route('my-tasks.attachments.store', $task), [
                'attachment' => $upload,
            ])
            ->assertRedirect();

        $attachment = $task->fresh()->attachments()->latest('id')->first();

        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists($attachment->path);
    }
}
