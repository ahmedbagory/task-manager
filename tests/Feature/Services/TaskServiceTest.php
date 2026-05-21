<?php

namespace Tests\Feature\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_manual_task_generates_unique_safe_task_numbers(): void
    {
        $actor = User::factory()->create();
        $service = app(TaskService::class);

        $taskOne = $service->createManualTask([
            'title' => 'Check HVAC issue',
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
        ], $actor);

        $taskTwo = $service->createManualTask([
            'title' => 'Water leakage report',
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
        ], $actor);

        $this->assertMatchesRegularExpression('/^TASK-\d{8}-\d{4}$/', $taskOne->task_number);
        $this->assertMatchesRegularExpression('/^TASK-\d{8}-\d{4}$/', $taskTwo->task_number);
        $this->assertNotSame($taskOne->task_number, $taskTwo->task_number);
        $this->assertSame(TaskSource::MANUAL, $taskOne->source);
    }

    public function test_create_task_from_whatsapp_message_links_message_and_sets_source(): void
    {
        $actor = User::factory()->create();
        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000001',
            'body' => 'Air conditioning is not working in floor 2.',
        ]);

        $task = app(TaskService::class)->createTaskFromWhatsAppMessage($message, [], $actor);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertSame(TaskSource::WHATSAPP, $task->source);
        $this->assertSame('+201000000001', $task->reported_by_phone);
        $this->assertSame($task->id, $message->fresh()->task_id);
    }

    public function test_complete_task_updates_status_and_completed_timestamp(): void
    {
        $actor = User::factory()->create();
        $task = Task::factory()->create([
            'status' => TaskStatus::IN_PROGRESS->value,
            'source' => TaskSource::MANUAL->value,
            'completed_at' => null,
        ]);

        $updatedTask = app(TaskService::class)->completeTask($task, $actor);

        $this->assertSame(TaskStatus::COMPLETED, $updatedTask->status);
        $this->assertNotNull($updatedTask->completed_at);
        $this->assertSame($actor->id, $updatedTask->updated_by);
    }
}
