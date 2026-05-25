<?php

namespace App\Http\Resources\Api;

use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskAttachment */
class TaskAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = $this->original_name ?: basename((string) $this->path);

        return [
            'id' => $this->id,
            'name' => $name,
            'filename' => basename((string) $this->path),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'type' => $this->type,
            'url' => $this->task_id
                ? route('api.mobile.my-tasks.attachments.download', [
                    'task' => $this->task_id,
                    'attachment' => $this->id,
                ])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->user
                ? (new UserResource($this->user))->resolve()
                : null,
        ];
    }
}
