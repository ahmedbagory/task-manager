<?php

namespace App\Services\Tasks;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Services\WhatsApp\TaskWhatsAppNotificationService;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        private readonly TaskNumberGenerator $taskNumberGenerator,
        private readonly TaskWhatsAppNotificationService $taskWhatsAppNotificationService,
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManualTask(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor): Task {
            unset($data['task_number'], $data['created_by'], $data['updated_by'], $data['source']);

            $data['task_number'] = $this->taskNumberGenerator->generate();
            $data['source'] = TaskSource::MANUAL->value;
            $data['status'] ??= TaskStatus::PENDING_ASSIGNMENT->value;
            $data['created_by'] = $actor->id;
            $data['updated_by'] = $actor->id;

            return Task::query()->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTaskFromWhatsAppMessage(WhatsappMessage $message, array $data = [], ?User $actor = null): Task
    {
        return DB::transaction(function () use ($message, $data, $actor): Task {
            $lockedMessage = WhatsappMessage::query()->lockForUpdate()->findOrFail($message->id);

            if (filled($lockedMessage->task_id)) {
                return Task::query()->findOrFail($lockedMessage->task_id);
            }

            $title = (string) ($data['title'] ?? str($lockedMessage->body ?: 'WhatsApp issue report')->limit(100));
            $description = $data['description'] ?? $lockedMessage->body;
            $reporterPhone = $data['reported_by_phone'] ?? $lockedMessage->from_phone ?? $lockedMessage->contact?->phone;

            $task = Task::query()->create([
                'task_number' => $this->taskNumberGenerator->generate(),
                'title' => filled($title) ? $title : 'WhatsApp issue report',
                'description' => $description,
                'department_id' => $data['department_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'reported_by_user_id' => $data['reported_by_user_id'] ?? null,
                'reported_by_phone' => $reporterPhone,
                'priority' => $data['priority'] ?? 'medium',
                'status' => $data['status'] ?? TaskStatus::PENDING_ASSIGNMENT->value,
                'source' => TaskSource::WHATSAPP->value,
                'location' => $data['location'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $lockedMessage->update(['task_id' => $task->id]);

            DB::afterCommit(function () use ($task): void {
                $freshTask = Task::query()->find($task->id);

                if ($freshTask) {
                    $this->taskWhatsAppNotificationService->notifyTaskRegistered($freshTask);
                }
            });

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTask(Task $task, array $data, User $actor): Task
    {
        unset($data['task_number'], $data['created_by'], $data['updated_by'], $data['source']);

        return DB::transaction(function () use ($task, $data, $actor): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if (in_array($lockedTask->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true)) {
                unset($data['priority'], $data['due_at'], $data['department_id'], $data['category_id']);
            }

            $lockedTask->fill($data);
            $lockedTask->updated_by = $actor->id;
            $lockedTask->save();

            return $lockedTask->refresh();
        });
    }

    public function changeStatus(Task $task, TaskStatus|string $status, ?User $actor = null): Task
    {
        $targetStatus = $status instanceof TaskStatus ? $status : TaskStatus::from($status);

        return DB::transaction(function () use ($task, $targetStatus, $actor): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $wasCompleted = $lockedTask->status === TaskStatus::COMPLETED;

            $lockedTask->status = $targetStatus;
            $lockedTask->updated_by = $actor?->id;

            if ($targetStatus === TaskStatus::IN_PROGRESS && is_null($lockedTask->started_at)) {
                $lockedTask->started_at = now();
            }

            if ($targetStatus === TaskStatus::COMPLETED) {
                $lockedTask->completed_at = now();
            }

            if (in_array($targetStatus, [TaskStatus::CANCELLED, TaskStatus::REJECTED], true)) {
                $lockedTask->assigned_to_user_id = null;
            }

            $lockedTask->save();

            if ($targetStatus === TaskStatus::COMPLETED && (! $wasCompleted)) {
                DB::afterCommit(function () use ($lockedTask): void {
                    $freshTask = Task::query()->find($lockedTask->id);

                    if ($freshTask) {
                        $this->taskWhatsAppNotificationService->notifyTaskCompleted($freshTask);
                        $this->taskWorkflowNotificationService->notifyTaskCompletedToDispatchers($freshTask);
                    }
                });
            }

            return $lockedTask->refresh();
        });
    }

    public function completeTask(Task $task, ?User $actor = null): Task
    {
        return $this->changeStatus($task, TaskStatus::COMPLETED, $actor);
    }

    public function cancelTask(Task $task, ?User $actor = null, ?string $reason = null): Task
    {
        return DB::transaction(function () use ($task, $actor, $reason): Task {
            $cancelledTask = $this->changeStatus($task, TaskStatus::CANCELLED, $actor);

            $reason = trim((string) $reason);

            if ($reason !== '') {
                $cancelledTask->description = trim(($cancelledTask->description ? $cancelledTask->description.PHP_EOL.PHP_EOL : '')."Cancellation reason: {$reason}");
                $cancelledTask->save();
            }

            return $cancelledTask->refresh();
        });
    }
}
