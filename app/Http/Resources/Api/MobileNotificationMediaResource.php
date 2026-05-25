<?php

namespace App\Http\Resources\Api;

use App\Models\MobileNotificationAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileNotificationAttachment */
class MobileNotificationMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $filename = $this->original_name ?: basename((string) $this->path);
        $url = $this->mobile_notification_id
            ? route('api.mobile.notifications.attachments.download', [
                'notification' => $this->mobile_notification_id,
                'attachment' => $this->id,
            ])
            : null;
        $type = $this->isImage()
            ? 'image'
            : ($this->isVideo() ? 'video' : 'file');

        return [
            'id' => $this->id,
            'type' => $type,
            'mime_type' => $this->mime_type,
            'filename' => $filename,
            'name' => $filename,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'url' => $url,
            'thumbnail_url' => $this->isImage() ? $url : null,
            'source' => 'notification_media',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
