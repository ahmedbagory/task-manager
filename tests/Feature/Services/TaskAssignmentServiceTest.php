<?php

namespace Tests\Feature\Services;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_task_prevents_assigning_completed_or_cancelled_tasks(): void
    {
        $service = app(TaskAssignmentService::class);
        $assignee = User::factory()->create();
        $dispatcher = User::factory()->create();

        $completedTask = Task::factory()->create([
            'status' => TaskStatus::COMPLETED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $this->expectException(ValidationException::class);

        $service->assignTask($completedTask, $assignee->id, $dispatcher, 'Please handle this.');
    }

    public function test_assign_task_prevents_duplicate_active_assignment(): void
    {
        $service = app(TaskAssignmentService::class);
        $assigneeOne = User::factory()->create();
        $assigneeTwo = User::factory()->create();
        $dispatcher = User::factory()->create();

        $task = Task::factory()->create([
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $service->assignTask($task, $assigneeOne->id, $dispatcher, 'First assignment');

        $this->expectException(ValidationException::class);

        $service->assignTask($task, $assigneeTwo->id, $dispatcher, 'Second assignment');
    }

    public function test_reject_assignment_requires_reason(): void
    {
        $service = app(TaskAssignmentService::class);
        $assignee = User::factory()->create();

        $assignment = TaskAssignment::factory()->create([
            'assigned_to_user_id' => $assignee->id,
            'status' => TaskAssignmentStatus::ASSIGNED->value,
        ]);

        $this->expectException(ValidationException::class);

        $service->rejectAssignment($assignment, $assignee, '   ');
    }

    public function test_assignment_lifecycle_updates_task_status_correctly(): void
    {
        $service = app(TaskAssignmentService::class);

        $dispatcher = User::factory()->create();
        $assignee = User::factory()->create();

        $task = Task::factory()->create([
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'assigned_to_user_id' => null,
            'source' => TaskSource::MANUAL->value,
            'started_at' => null,
            'completed_at' => null,
        ]);

        $assignment = $service->assignTask($task, $assignee->id, $dispatcher, 'Assigned from dispatcher');

        $this->assertSame(TaskAssignmentStatus::ASSIGNED, $assignment->status);
        $this->assertSame(TaskStatus::ASSIGNED, $task->fresh()->status);

        $assignment = $service->acceptAssignment($assignment, $assignee);
        $this->assertSame(TaskAssignmentStatus::ACCEPTED, $assignment->status);
        $this->assertNotNull($assignment->accepted_at);

        $assignment = $service->startTask($assignment, $assignee);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->started_at);

        $assignment = $service->completeAssignedTask($assignment, $assignee);

        $this->assertSame(TaskAssignmentStatus::COMPLETED, $assignment->status);
        $this->assertNotNull($assignment->completed_at);
        $this->assertSame(TaskStatus::AWAITING_REPORTER_CONFIRMATION, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_reporter_confirmation_closes_task_and_rejection_reopens_it(): void
    {
        $service = app(TaskAssignmentService::class);

        $dispatcher = User::factory()->create();
        $assignee = User::factory()->create();
        $reporter = User::factory()->create();

        $task = Task::factory()->create([
            'reported_by_user_id' => $reporter->id,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'assigned_to_user_id' => null,
            'source' => TaskSource::MANUAL->value,
        ]);

        $assignment = $service->assignTask($task, $assignee->id, $dispatcher, 'Assigned from dispatcher');
        $service->acceptAssignment($assignment, $assignee);
        $service->startTask($assignment, $assignee);
        $service->completeAssignedTask($assignment, $assignee);

        $this->assertSame(TaskStatus::AWAITING_REPORTER_CONFIRMATION, $task->fresh()->status);

        $service->rejectResolution($task, $reporter, 'المشكلة لم تحل بعد.');

        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
        $this->assertSame(
            TaskAssignmentStatus::ACCEPTED,
            $task->fresh()->assignments()->latest('id')->first()->status
        );

        $service->completeAssignedTask($task->fresh()->assignments()->latest('id')->first(), $assignee);
        $service->confirmResolution($task, $reporter);

        $this->assertSame(TaskStatus::COMPLETED, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }
}
