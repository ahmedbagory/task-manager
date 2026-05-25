<?php

namespace App\Http\Resources\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Task;
use App\Services\Tasks\TaskAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $priority = $this->priority instanceof TaskPriority
            ? $this->priority
            : TaskPriority::tryFrom((string) $this->priority);
        $source = $this->source instanceof TaskSource
            ? $this->source
            : TaskSource::tryFrom((string) $this->source);

        $status = $this->workflowStatus();

        $taskAccessService = app(TaskAccessService::class);
        $requesterUser = $taskAccessService->resolveRequesterUser($this->resource);
        $assignedByUser = $this->latestAssignment?->assignedByUser ?? $this->createdByUser;

        $commentsCount = $this->relationLoaded('comments')
            ? $this->comments->count()
            : (int) ($this->comments_count ?? 0);
        $attachmentsCount = $this->relationLoaded('attachments')
            ? $this->attachments->count()
            : (int) ($this->attachments_count ?? 0);

        $myAssignment = $taskAccessService->resolveMyAssignment($this->resource, $request->user());
        $assignees = $taskAccessService->resolveAssignees($this->resource)
            ->map(fn ($user): array => (new UserResource($user))->resolve())
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'display_number' => $this->resource->displayNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'reported_by_phone' => $this->reported_by_phone,
            'location' => $this->location,
            'source' => $source?->value ?? (is_string($this->source) ? $this->source : null),
            'source_label' => $source?->label(),
            'status' => $status->value,
            'status_label' => $this->workflowStatusLabel(),
            'priority' => $priority?->value ?? (string) $this->priority,
            'priority_label' => $priority?->label() ?? str((string) $this->priority)->replace('_', ' ')->title()->toString(),
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'hierarchy_name' => $this->department->hierarchy_name,
                'code' => $this->department->code,
                'parent_id' => $this->department->parent_id,
            ] : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
            ] : null,
            'due_at' => $this->due_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assigned_to' => $this->assignedToUser
                ? (new UserResource($this->assignedToUser))->resolve()
                : null,
            'assignees' => $assignees,
            'requester' => $requesterUser
                ? (new UserResource($requesterUser))->resolve()
                : null,
            'reporter' => $taskAccessService->resolveReporter($this->resource),
            'requested_by' => $requesterUser
                ? (new UserResource($requesterUser))->resolve()
                : null,
            'assigned_by' => $assignedByUser
                ? (new UserResource($assignedByUser))->resolve()
                : null,
            'created_by' => $this->createdByUser
                ? (new UserResource($this->createdByUser))->resolve()
                : null,
            'comments_count' => $commentsCount,
            'attachments_count' => $attachmentsCount,
            'my_assignment' => $myAssignment,
            'current_user_role_on_task' => $taskAccessService->resolveCurrentUserRole($this->resource, $request->user()),
            'allowed_actions' => $taskAccessService->resolveAllowedActions($this->resource, $request->user()),
            'resolution_state' => $taskAccessService->resolveResolutionState($this->resource),
        ];
    }
}
