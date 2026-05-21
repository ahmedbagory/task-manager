<?php

namespace Tests\Feature\Api;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_sanctum_token_and_safe_user_payload(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);
        $employee->assignRole(Rbac::EMPLOYEE);

        $response = $this->postJson('/api/mobile/login', [
            'email' => 'employee@example.com',
            'password' => 'password',
            'device_name' => 'postman',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'employee@example.com')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email'],
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_unauthenticated_requests_receive_consistent_json_error(): void
    {
        $this->getJson('/api/mobile/my-tasks')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors.auth.0', 'Authentication is required.');
    }

    public function test_employee_can_only_list_and_view_own_assigned_tasks(): void
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

        Sanctum::actingAs($employee);

        $listResponse = $this->getJson('/api/mobile/my-tasks');
        $listResponse->assertOk();

        $taskIds = collect($listResponse->json('data.tasks'))->pluck('id')->all();
        $this->assertContains($myTask->id, $taskIds);
        $this->assertNotContains($otherTask->id, $taskIds);

        $this->getJson("/api/mobile/my-tasks/{$myTask->id}")
            ->assertOk()
            ->assertJsonPath('data.task.id', $myTask->id);

        $this->getJson("/api/mobile/my-tasks/{$otherTask->id}")
            ->assertNotFound();
    }

    public function test_employee_can_accept_start_comment_upload_and_complete_assigned_task(): void
    {
        app(RbacInitializationService::class)->seed();
        Storage::fake('local');

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

        Sanctum::actingAs($employee);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.task.status.value', TaskStatus::ASSIGNED->value);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/start")
            ->assertOk()
            ->assertJsonPath('data.task.status.value', TaskStatus::IN_PROGRESS->value);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/comment", [
            'comment' => 'Work started and inspection in progress.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.comment.comment', 'Work started and inspection in progress.');

        $upload = UploadedFile::fake()->image('proof.jpg');

        $uploadResponse = $this->postJson("/api/mobile/my-tasks/{$task->id}/attachments", [
            'attachment' => $upload,
        ]);
        $uploadResponse
            ->assertCreated()
            ->assertJsonPath('data.attachment.original_name', 'proof.jpg');

        $attachmentId = $uploadResponse->json('data.attachment.id');
        $storedAttachment = $task->fresh()->attachments()->findOrFail($attachmentId);
        Storage::disk('local')->assertExists($storedAttachment->path);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.task.status.value', TaskStatus::COMPLETED->value);

        $this->assertSame(
            TaskAssignmentStatus::COMPLETED,
            $task->fresh()->assignments()->latest('id')->first()->status
        );
    }

    public function test_reject_requires_reason_and_updates_task_status_to_pending_assignment(): void
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

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Urgent task assignment.');

        Sanctum::actingAs($employee);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.reason.0', 'The reason field is required.');

        $this->postJson("/api/mobile/my-tasks/{$task->id}/reject", [
            'reason' => 'Requires a specialist from another team.',
        ])
            ->assertOk()
            ->assertJsonPath('data.task.status.value', TaskStatus::PENDING_ASSIGNMENT->value);

        $task->refresh();

        $this->assertNull($task->assigned_to_user_id);
        $this->assertSame(
            TaskAssignmentStatus::REJECTED,
            $task->assignments()->latest('id')->first()->status
        );
    }
}
