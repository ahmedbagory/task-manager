<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['task_id', 'target_type', 'target_id', 'assigned_by'])]
class TaskAssignmentTarget extends Model
{
    public const LEGACY_ALL = 'all';

    public const USER_ALIAS = 'user';

    public const DEPARTMENT_ALIAS = 'department';

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

    /**
     * @return array<int, string>
     */
    public static function userTargetTypes(): array
    {
        return [
            self::USER_ALIAS,
            User::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function departmentTargetTypes(): array
    {
        return [
            self::DEPARTMENT_ALIAS,
            Department::class,
        ];
    }

    public function isLegacyAllTarget(): bool
    {
        return $this->target_type === self::LEGACY_ALL;
    }

    public function isUserTarget(): bool
    {
        return in_array($this->target_type, self::userTargetTypes(), true);
    }

    public function isDepartmentTarget(): bool
    {
        return in_array($this->target_type, self::departmentTargetTypes(), true);
    }

    public function normalizedTargetType(): string
    {
        if ($this->isLegacyAllTarget()) {
            return self::LEGACY_ALL;
        }

        if ($this->isUserTarget()) {
            return self::USER_ALIAS;
        }

        if ($this->isDepartmentTarget()) {
            return self::DEPARTMENT_ALIAS;
        }

        return (string) $this->target_type;
    }

    public function getTargetNameAttribute(): string
    {
        if ($this->isLegacyAllTarget()) {
            return __('جميع الموظفين');
        }

        if ($this->isUserTarget()) {
            return $this->target?->name ?? '—';
        }

        if ($this->isDepartmentTarget()) {
            return $this->target?->hierarchy_name ?? '—';
        }

        return '—';
    }

    public function getTargetTypeLabelAttribute(): string
    {
        if ($this->isLegacyAllTarget()) {
            return 'الكل';
        }

        if ($this->isUserTarget()) {
            return 'موظف';
        }

        if ($this->isDepartmentTarget()) {
            return $this->target?->parent_id ? 'فرع' : 'قسم';
        }

        return (string) $this->target_type;
    }
}
