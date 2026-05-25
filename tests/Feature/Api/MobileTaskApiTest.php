<?php

namespace Tests\Feature\Api;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\DeviceToken;
use App\Models\Task;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Tasks\TaskAssignmentService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
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

    public function test_authenticated_user_can_register_refresh_and_remove_a_device_token(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        Sanctum::actingAs($employee);

        $this->postJson('/api/mobile/device-token', [
            'device_id' => 'device-123',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
            'fcm_token' => 'token-one',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $employee->id,
            'device_id' => 'device-123',
            'fcm_token' => 'token-one',
            'device_name' => 'Pixel 8',
        ]);

        $this->postJson('/api/mobile/device-token', [
            'device_id' => 'device-123',
            'device_name' => 'Pixel 8',
            'device_type' => 'android',
            'fcm_token' => 'token-two',
        ])->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'device_id' => 'device-123',
            'fcm_token' => 'token-one',
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'device_id' => 'device-123',
            'fcm_token' => 'token-two',
        ]);
        $this->assertSame(1, DeviceToken::query()->count());

        $this->deleteJson('/api/mobile/device-token', [
            'device_id' => 'device-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_admin_can_send_test_notification_to_selected_mobile_device(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        DeviceToken::query()->create([
            'user_id' => $employee->id,
            'device_id' => 'device-abc',
            'fcm_token' => 'fcm-token-abc',
            'device_type' => 'android',
        ]);

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('sendTestNotification')
                ->once()
                ->withArgs(function (User $recipient, string $title, string $body, array $data, ?string $deviceId) use ($employee): bool {
                    return $recipient->is($employee)
                        && $title === 'Ping'
                        && $body === 'Mobile push test'
                        && $data['type'] === 'test_notification'
                        && $deviceId === 'device-abc';
                })
                ->andReturn(1);
        });

        Sanctum::actingAs($admin);

        $this->postJson('/api/mobile/notifications/test', [
            'user_id' => $employee->id,
            'device_id' => 'device-abc',
            'title' => 'Ping',
            'body' => 'Mobile push test',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', 1);
    }

    public function test_employee_cannot_send_test_notification_without_permission(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        Sanctum::actingAs($employee);

        $this->postJson('/api/mobile/notifications/test', [
            'user_id' => $employee->id,
            'title' => 'Ping',
            'body' => 'Mobile push test',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.');
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
            ->assertJsonPath('data.task.status', TaskStatus::ACCEPTED->value);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/start")
            ->assertOk()
            ->assertJsonPath('data.task.status', TaskStatus::IN_PROGRESS->value);

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
            ->assertJsonPath('data.task.status', TaskStatus::AWAITING_REPORTER_CONFIRMATION->value);

        $this->assertSame(
            TaskAssignmentStatus::COMPLETED,
            $task->fresh()->assignments()->latest('id')->first()->status
        );
    }

    public function test_reporter_can_confirm_or_reject_resolution_using_allowed_actions_flow(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $reporter = User::factory()->create();
        $reporter->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'reported_by_user_id' => $reporter->id,
            'assigned_to_user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        $assignment = app(TaskAssignmentService::class)->assignTask(
            $task,
            $employee->id,
            $dispatcher,
            'Please handle this issue.'
        );
        app(TaskAssignmentService::class)->acceptAssignment($assignment, $employee);
        app(TaskAssignmentService::class)->startTask($assignment, $employee);
        app(TaskAssignmentService::class)->completeAssignedTask($assignment, $employee);

        Sanctum::actingAs($reporter);

        $this->getJson("/api/mobile/my-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.task.current_user_role_on_task', 'requester')
            ->assertJsonPath('data.task.requester.id', $reporter->id)
            ->assertJsonPath('data.task.allowed_actions.can_confirm_resolution', true)
            ->assertJsonPath('data.task.allowed_actions.can_reject_resolution', true)
            ->assertJsonPath('data.task.allowed_actions.can_mark_resolved', false);

        $this->postJson("/api/mobile/my-tasks/{$task->id}/reject-resolution", [
            'comment' => 'المشكلة ما زالت قائمة.',
        ])
            ->assertOk()
            ->assertJsonPath('data.task.status', TaskStatus::REOPENED->value);

        $this->assertSame(
            TaskAssignmentStatus::ACCEPTED,
            $task->fresh()->assignments()->latest('id')->first()->status
        );

        app(TaskAssignmentService::class)->completeAssignedTask(
            $task->fresh()->assignments()->latest('id')->first(),
            $employee,
        );

        $this->postJson("/api/mobile/my-tasks/{$task->id}/confirm-resolution")
            ->assertOk()
            ->assertJsonPath('data.task.status', TaskStatus::COMPLETED->value);
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
            ->assertJsonPath('data.task.status', TaskStatus::PENDING_ASSIGNMENT->value);

        $task->refresh();

        $this->assertNull($task->assigned_to_user_id);
        $this->assertSame(
            TaskAssignmentStatus::REJECTED,
            $task->assignments()->latest('id')->first()->status
        );
    }

    public function test_targeted_task_is_listed_as_pending_acceptance_not_pending_assignment(): void
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

        TaskAssignmentTarget::query()->create([
            'task_id' => $task->id,
            'target_type' => User::class,
            'target_id' => $employee->id,
            'assigned_by' => $dispatcher->id,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/mobile/my-tasks')
            ->assertOk()
            ->assertJsonPath('data.tasks.0.id', $task->id)
            ->assertJsonPath('data.tasks.0.status', TaskStatus::ASSIGNED->value)
            ->assertJsonPath('data.tasks.0.my_assignment.status', TaskStatus::ASSIGNED->value)
            ->assertJsonPath('data.tasks.0.my_assignment.can_accept', true);
    }

    public function test_task_detail_returns_source_label_and_attachment_payload(): void
    {
        app(RbacInitializationService::class)->seed();
        Storage::fake('local');

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'reported_by_user_id' => $employee->id,
            'status' => TaskStatus::NEW->value,
            'source' => TaskSource::WHATSAPP->value,
        ]);

        $storedPath = 'task-attachments/'.$task->id.'/evidence.jpg';
        Storage::disk('local')->put($storedPath, 'image-bytes');
        $attachment = $task->attachments()->create([
            'user_id' => $employee->id,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => 'evidence.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'type' => 'image',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/mobile/my-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.task.source', TaskSource::WHATSAPP->value)
            ->assertJsonPath('data.task.source_label', 'واتساب')
            ->assertJsonPath('data.task.attachments.0.id', $attachment->id)
            ->assertJsonPath('data.task.attachments.0.name', 'evidence.jpg')
            ->assertJsonPath('data.task.attachments.0.filename', 'evidence.jpg')
            ->assertJsonPath('data.task.attachments.0.mime_type', 'image/jpeg')
            ->assertJsonPath('data.task.attachments.0.size', 11)
            ->assertJsonPath('data.task.attachments.0.type', 'image')
            ->assertJsonPath('data.task.attachments.0.thumbnail_url', route('api.mobile.my-tasks.attachments.download', [
                'task' => $task->id,
                'attachment' => $attachment->id,
            ]));
    }
}
