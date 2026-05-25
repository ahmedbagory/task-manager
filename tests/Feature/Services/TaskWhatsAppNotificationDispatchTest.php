<?php

namespace Tests\Feature\Services;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Jobs\SendWhatsAppTextMessageJob;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Tasks\TaskAssignmentService;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskWhatsAppNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_created_from_whatsapp_dispatches_registration_notification_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000100',
            'body' => 'Issue reported from WhatsApp',
            'message_type' => 'text',
            'media_url' => null,
        ]);

        $task = app(TaskService::class)->createTaskFromWhatsAppMessage($message, [], $actor);

        Queue::assertPushed(SendWhatsAppTextMessageJob::class, function (SendWhatsAppTextMessageJob $job) use ($task): bool {
            return $job->taskId === $task->id
                && $job->phone === '+201000000100'
                && str_contains($job->message, $task->displayNumber())
                && str_contains($job->message, 'تم تسجيل بلاغك رقم');
        });
    }

    public function test_assigning_whatsapp_task_dispatches_assignment_notification_job(): void
    {
        Queue::fake();

        $dispatcher = User::factory()->create();
        $employee = User::factory()->create();

        $task = Task::factory()->create([
            'source' => TaskSource::WHATSAPP->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'reported_by_phone' => '+201000000101',
            'assigned_to_user_id' => null,
        ]);

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Assign to employee');

        Queue::assertPushed(SendWhatsAppTextMessageJob::class, function (SendWhatsAppTextMessageJob $job) use ($task): bool {
            return $job->taskId === $task->id
                && $job->phone === '+201000000101'
                && str_contains($job->message, $task->displayNumber())
                && str_contains($job->message, 'تم تحويل البلاغ رقم');
        });
    }

    public function test_completing_whatsapp_task_dispatches_completion_notification_job(): void
    {
        Queue::fake();

        $dispatcher = User::factory()->create();
        $employee = User::factory()->create();
        $reporter = User::factory()->create();

        $task = Task::factory()->create([
            'source' => TaskSource::WHATSAPP->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'reported_by_phone' => '+201000000102',
            'reported_by_user_id' => $reporter->id,
            'assigned_to_user_id' => null,
        ]);

        $assignment = app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Assign and complete');
        app(TaskAssignmentService::class)->acceptAssignment($assignment, $employee);
        app(TaskAssignmentService::class)->startTask($assignment, $employee);
        app(TaskAssignmentService::class)->completeAssignedTask($assignment, $employee);
        app(TaskAssignmentService::class)->confirmResolution($task->fresh(), $reporter);

        Queue::assertPushed(SendWhatsAppTextMessageJob::class, function (SendWhatsAppTextMessageJob $job) use ($task): bool {
            return $job->taskId === $task->id
                && $job->phone === '+201000000102'
                && str_contains($job->message, $task->displayNumber())
                && str_contains($job->message, 'تم إغلاق البلاغ رقم');
        });
    }

    public function test_manual_task_does_not_dispatch_whatsapp_notification_jobs(): void
    {
        Queue::fake();

        $dispatcher = User::factory()->create();
        $employee = User::factory()->create();

        $task = Task::factory()->create([
            'source' => TaskSource::MANUAL->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'reported_by_phone' => '+201000000103',
            'assigned_to_user_id' => null,
        ]);

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'No outbound for manual source');

        Queue::assertNotPushed(SendWhatsAppTextMessageJob::class);
    }
}
