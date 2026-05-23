<?php

namespace App\Http\Resources\Api;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new TaskListResource($this->resource))->toArray($request);

        $base['assigned_to'] = $this->assignedToUser
            ? (new UserResource($this->assignedToUser))->resolve()
            : null;
        $base['assignments'] = TaskAssignmentResource::collection($this->assignments)->resolve();
        $base['comments'] = TaskCommentResource::collection($this->comments)->resolve();
        $base['attachments'] = TaskAttachmentResource::collection($this->attachments)->resolve();

        if ($this->relationLoaded('assignmentTargets')) {
            $base['assignment_targets'] = $this->assignmentTargets->map(fn ($target): array => [
                'type' => $target->target_type,
                'target_id' => $target->target_id,
                'name' => $target->target_name,
                'type_label' => $target->target_type_label,
            ])->values()->all();
        }

        return $base;
    }
}
