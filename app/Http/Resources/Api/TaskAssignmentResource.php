<?php

namespace App\Http\Resources\Api;

use App\Enums\TaskAssignmentStatus;
use App\Models\TaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskAssignment */
class TaskAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof TaskAssignmentStatus
            ? $this->status
            : TaskAssignmentStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'status' => [
                'value' => $status?->value ?? (string) $this->status,
                'label' => $status?->label() ?? str((string) $this->status)->replace('_', ' ')->title()->toString(),
            ],
            'note' => $this->note,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'assigned_to' => $this->assignedToUser
                ? (new UserResource($this->assignedToUser))->resolve()
                : null,
            'assigned_by' => $this->assignedByUser
                ? (new UserResource($this->assignedByUser))->resolve()
                : null,
        ];
    }
}
