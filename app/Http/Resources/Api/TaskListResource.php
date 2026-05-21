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
                'code' => $this->department->code,
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
        ];
    }
}
