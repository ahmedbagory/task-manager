<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'action', 'from_user_id', 'to_user_id', 'performed_by', 'note'])]
class TaskAssignmentHistory extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'assigned' => 'تعيين',
            'reassigned' => 'إعادة تعيين',
            'removed_assignee' => 'إزالة مكلف',
            'status_changed' => 'تغيير الحالة',
            'targets_updated' => 'تحديث الإسناد',
            'accepted' => 'قبول',
            'rejected' => 'رفض',
            'resolution_submitted' => 'إرسال للتأكيد',
            'reporter_confirmed' => 'تأكيد الحل',
            'reporter_rejected' => 'رفض تأكيد الحل',
            'completed' => 'إكمال',
            'started' => 'بدء العمل',
            default => $this->action,
        };
    }
}
