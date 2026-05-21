<?php

namespace App\Models;

use App\Enums\TaskAssignmentStatus;
use Database\Factories\TaskAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id',
    'assigned_to_user_id',
    'assigned_by_user_id',
    'note',
    'status',
    'assigned_at',
    'accepted_at',
    'completed_at',
])]
class TaskAssignment extends Model
{
    /** @use HasFactory<TaskAssignmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TaskAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
