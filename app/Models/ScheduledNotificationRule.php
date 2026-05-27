<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ScheduledNotificationRule extends Model
{
    protected $fillable = [
        'name',
        'title',
        'body',
        'type',
        'target_type',
        'target_payload',
        'frequency',
        'interval_hours',
        'start_at',
        'end_at',
        'next_run_at',
        'last_run_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_payload' => 'array',
            'interval_hours' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ScheduledNotificationLog::class, 'rule_id');
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        if (! $this->next_run_at) {
            return false;
        }

        return $this->next_run_at->lte(now());
    }

    public function computeNextRunAt(?Carbon $from = null): ?Carbon
    {
        $from ??= now();

        if ($this->frequency === 'once') {
            return null;
        }

        return match ($this->frequency) {
            'every_hours' => $from->copy()->addHours(max(1, $this->interval_hours ?? 1)),
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            'monthly' => $from->copy()->addMonth(),
            default => $from->copy()->addDay(),
        };
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'once' => 'مرة واحدة',
            'every_hours' => 'كل ' . ($this->interval_hours ?? 1) . ' ساعات',
            'daily' => 'يومي',
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            default => $this->frequency,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'general' => 'عام',
            'overdue_tasks' => 'مهام متأخرة',
            'pending_assignment' => 'بانتظار الإسناد',
            'pending_confirmation' => 'بانتظار تأكيد صاحب الطلب',
            default => $this->type,
        };
    }

    public function targetLabel(): string
    {
        return match ($this->target_type) {
            'all' => 'جميع الموظفين',
            'users' => 'موظفين محددين',
            'departments' => 'أقسام محددة',
            'roles' => 'أدوار محددة',
            default => $this->target_type,
        };
    }
}
