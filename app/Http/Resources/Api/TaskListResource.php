<?php

namespace App\Http\Resources\Api;

use App\Enums\TaskPriority;
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

        $status = $this->workflowStatus();

        $requestedBy = $this->reportedByUser ?? $this->createdByUser;

        $assignedByUser = $this->latestAssignment?->assignedByUser;

        $commentsCount = $this->relationLoaded('comments')
            ? $this->comments->count()
            : (int) ($this->comments_count ?? 0);

        $taskAccessService = app(TaskAccessService::class);
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
            'source' => is_string($this->source) ? $this->source : $this->source?->value,
            'status' => [
                'value' => $status->value,
                'label' => $this->workflowStatusLabel(),
            ],
            'priority' => [
                'value' => $priority?->value ?? (string) $this->priority,
                'label' => $priority?->label() ?? str((string) $this->priority)->replace('_', ' ')->title()->toString(),
            ],
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
            'reporter' => $taskAccessService->resolveReporter($this->resource),
            'requested_by' => $requestedBy
                ? (new UserResource($requestedBy))->resolve()
                : null,
            'assigned_by' => $assignedByUser
                ? (new UserResource($assignedByUser))->resolve()
                : null,
            'created_by' => $this->createdByUser
                ? (new UserResource($this->createdByUser))->resolve()
                : null,
            'comments_count' => $commentsCount,
            'my_assignment' => $myAssignment,
            'current_user_role_on_task' => $taskAccessService->resolveCurrentUserRole($this->resource, $request->user()),
            'allowed_actions' => $taskAccessService->resolveAllowedActions($this->resource, $request->user()),
            'resolution_state' => $taskAccessService->resolveResolutionState($this->resource),
        ];
    }
}
