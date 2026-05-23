<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MobileNotificationAttachment extends Model
{
    protected $fillable = [
        'mobile_notification_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function mobileNotification(): BelongsTo
    {
        return $this->belongsTo(MobileNotification::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, self::IMAGE_MIMES, true);
    }

    public function isVideo(): bool
    {
        return in_array($this->mime_type, self::VIDEO_MIMES, true);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function humanSize(): string
    {
        if (! $this->size) {
            return '-';
        }

        if ($this->size < 1024) {
            return $this->size.' B';
        }

        if ($this->size < 1048576) {
            return number_format($this->size / 1024, 1).' KB';
        }

        return number_format($this->size / 1048576, 1).' MB';
    }

    public static function resolveType(string $mimeType): string
    {
        if (in_array($mimeType, self::IMAGE_MIMES, true)) {
            return 'image';
        }

        if (in_array($mimeType, self::VIDEO_MIMES, true)) {
            return 'video';
        }

        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (in_array($mimeType, $documentMimes, true)) {
            return 'document';
        }

        return 'other';
    }
}
