<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['task_id', 'target_type', 'target_id', 'assigned_by'])]
class TaskAssignmentTarget extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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
        return match ($this->target_type) {
            'user' => 'موظف',
            'department' => $this->target?->parent_id ? 'وحدة' : 'قسم رئيسي',
            default => $this->target_type,
        };
    }
}
