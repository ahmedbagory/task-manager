<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MobileNotificationTarget extends Model
{
    protected $fillable = [
        'mobile_notification_id',
        'target_type',
        'target_id',
    ];

    public function mobileNotification(): BelongsTo
    {
        return $this->belongsTo(MobileNotification::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function getTargetNameAttribute(): string
    {
        return match ($this->target_type) {
            'user' => $this->target?->name ?? '—',
            'department' => $this->target?->hierarchy_name ?? '—',
            default => '—',
        };
    }

    public function getTargetTypeLabelAttribute(): string
    {
        if ($this->target_type !== 'department') {
            return __('Employee');
        }

        return $this->target?->parent_id
            ? __('Specific Units')
            : __('Top-level Departments');
    }
}
