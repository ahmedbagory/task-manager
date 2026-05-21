<?php

namespace App\Models;

use Database\Factories\TaskAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'type'])]
class TaskAttachment extends Model
{
    /** @use HasFactory<TaskAttachmentFactory> */
    use HasFactory;

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

    private const EXCEL_MIMES = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel.sheet.macroEnabled.12',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, self::IMAGE_MIMES, true);
    }

    public function isVideo(): bool
    {
        return in_array($this->mime_type, self::VIDEO_MIMES, true);
    }

    public function isExcel(): bool
    {
        return in_array($this->mime_type, self::EXCEL_MIMES, true)
            || in_array($this->fileExtension(), ['xls', 'xlsx', 'xlsm'], true);
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isVideo() || $this->isExcel() || $this->mime_type === 'application/pdf';
    }

    public function fileExtension(): string
    {
        return strtolower(pathinfo($this->original_name ?: $this->path, PATHINFO_EXTENSION));
    }

    public function humanSize(): string
    {
        if (! $this->size) {
            return '-';
        }

        if ($this->size < 1024) {
            return $this->size . ' B';
        }

        if ($this->size < 1048576) {
            return number_format($this->size / 1024, 1) . ' KB';
        }

        return number_format($this->size / 1048576, 1) . ' MB';
    }
}
