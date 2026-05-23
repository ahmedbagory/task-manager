<?php

namespace App\Http\Resources\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
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

        $status = $this->status instanceof TaskStatus
            ? $this->status
            : TaskStatus::tryFrom((string) $this->status);

        $requestedBy = $this->latestAssignment?->assignedByUser
            ?? $this->reportedByUser
            ?? $this->createdByUser;

        $commentsCount = $this->relationLoaded('comments')
            ? $this->comments->count()
            : (int) ($this->comments_count ?? 0);

        $myAssignment = $this->resolveMyAssignment($request->user());

        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'reported_by_phone' => $this->reported_by_phone,
            'location' => $this->location,
            'source' => is_string($this->source) ? $this->source : $this->source?->value,
            'status' => [
                'value' => $status?->value ?? (string) $this->status,
                'label' => $status?->label() ?? str((string) $this->status)->replace('_', ' ')->title()->toString(),
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
            'requested_by' => $requestedBy
                ? (new UserResource($requestedBy))->resolve()
                : null,
            'comments_count' => $commentsCount,
            'my_assignment' => $myAssignment,
        ];
    }

    /**
     * @return array{status: string, is_direct: bool, can_accept: bool}|null
     */
    private function resolveMyAssignment(?\App\Models\User $user): ?array
    {
        if (! $user) {
            return null;
        }

        if ($this->assigned_to_user_id === $user->id) {
            $assignment = $this->relationLoaded('assignments')
                ? $this->assignments->where('assigned_to_user_id', $user->id)->sortByDesc('id')->first()
                : null;

            return [
                'status' => $assignment?->status?->value ?? 'assigned',
                'is_direct' => true,
                'can_accept' => $assignment?->status?->value === 'assigned',
            ];
        }

        return [
            'status' => 'pending',
            'is_direct' => false,
            'can_accept' => true,
        ];
    }
}
