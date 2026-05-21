<?php

namespace App\Http\Resources\Api;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskComment */
class TaskCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comment' => $this->comment,
            'is_internal' => (bool) $this->is_internal,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user
                ? (new UserResource($this->user))->resolve()
                : null,
        ];
    }
}
