<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'task_number',
    'title',
    'description',
    'department_id',
    'category_id',
    'reported_by_user_id',
    'reported_by_phone',
    'assigned_to_user_id',
    'priority',
    'status',
    'source',
    'location',
    'due_at',
    'started_at',
    'completed_at',
    'created_by',
    'updated_by',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'source' => TaskSource::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function hasActiveAssignee(): bool
    {
        return filled($this->assigned_to_user_id);
    }

    public function hasValidAssignmentTargets(): bool
    {
        if ($this->relationLoaded('assignmentTargets')) {
            return $this->assignmentTargets
                ->contains(fn (TaskAssignmentTarget $target): bool => $this->isRecognizedAssignmentTarget($target));
        }

        return $this->assignmentTargets()
            ->whereIn('target_type', $this->recognizedAssignmentTargetTypes())
            ->exists();
    }

    public function workflowStatus(): TaskStatus
    {
        $status = $this->status instanceof TaskStatus
            ? $this->status
            : TaskStatus::from((string) $this->status);

        if (in_array($status, [
            TaskStatus::ACCEPTED,
            TaskStatus::IN_PROGRESS,
            TaskStatus::WAIT_RESPONSE,
            TaskStatus::COMPLETED,
            TaskStatus::CANCELLED,
        ], true)) {
            return $status;
        }

        if ($this->hasActiveAssignee() || $this->hasValidAssignmentTargets()) {
            return TaskStatus::ASSIGNED;
        }

        return TaskStatus::PENDING_ASSIGNMENT;
    }

    public function workflowStatusLabel(): string
    {
        if ($this->isPendingAcceptanceState()) {
            return __('Pending Acceptance');
        }

        return $this->workflowStatus()->label();
    }

    public function isPendingAcceptanceState(): bool
    {
        return (! $this->hasActiveAssignee())
            && $this->hasValidAssignmentTargets()
            && $this->workflowStatus() === TaskStatus::ASSIGNED;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function latestAssignment(): HasOne
    {
        return $this->hasOne(TaskAssignment::class)->latestOfMany();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function assignmentTargets(): HasMany
    {
        return $this->hasMany(TaskAssignmentTarget::class);
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(TaskAssignmentHistory::class)->orderByDesc('created_at');
    }

    /**
     * @return array<int, string>
     */
    private function recognizedAssignmentTargetTypes(): array
    {
        return array_values(array_unique([
            ...TaskAssignmentTarget::userTargetTypes(),
            ...TaskAssignmentTarget::departmentTargetTypes(),
            TaskAssignmentTarget::LEGACY_ALL,
        ]));
    }

    private function isRecognizedAssignmentTarget(TaskAssignmentTarget $target): bool
    {
        return in_array($target->target_type, $this->recognizedAssignmentTargetTypes(), true);
    }
}
