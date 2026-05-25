<?php

namespace App\Services\Tasks;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class TaskAssigneeSyncService
{
    /**
     * @param  array<int, int|string>|Collection<int, int|string>  $assigneeIds
     * @return array{desired_ids: array<int, int>, current_ids: array<int, int>, added_ids: array<int, int>, removed_ids: array<int, int>}
     */
    public function sync(Task $task, array|Collection $assigneeIds, User $actor): array
    {
        $desiredIds = $this->normalizeIds($assigneeIds);
        $latestAssignments = $this->latestAssignmentsByUser($task);

        $currentIds = $latestAssignments
            ->filter(fn (TaskAssignment $assignment): bool => $assignment->status !== TaskAssignmentStatus::REJECTED)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $addedIds = array_values(array_diff($desiredIds, $currentIds));
        $removedIds = array_values(array_diff($currentIds, $desiredIds));

        foreach ($addedIds as $userId) {
            $task->assignments()->create([
                'assigned_to_user_id' => $userId,
                'assigned_by_user_id' => $actor->id,
                'status' => TaskAssignmentStatus::ASSIGNED->value,
                'assigned_at' => now(),
            ]);

            TaskAssignmentHistory::query()->create([
                'task_id' => $task->id,
                'action' => 'assigned',
                'to_user_id' => $userId,
                'performed_by' => $actor->id,
                'note' => __('تمت إضافة المكلف إلى المهمة.'),
            ]);
        }

        foreach ($removedIds as $userId) {
            $latestAssignment = $latestAssignments->get($userId);

            if (! $latestAssignment instanceof TaskAssignment) {
                continue;
            }

            if (in_array($latestAssignment->status, [
                TaskAssignmentStatus::ASSIGNED,
                TaskAssignmentStatus::ACCEPTED,
            ], true)) {
                $latestAssignment->forceFill([
                    'status' => TaskAssignmentStatus::REJECTED,
                    'note' => __('تمت إزالة المكلف من المهمة.'),
                ])->save();
            }

            TaskAssignmentHistory::query()->create([
                'task_id' => $task->id,
                'action' => 'removed_assignee',
                'to_user_id' => (int) $userId,
                'performed_by' => $actor->id,
                'note' => __('تمت إزالة المكلف من المهمة.'),
            ]);
        }

        $this->refreshPrimaryAssignee($task, $actor);

        return [
            'desired_ids' => $desiredIds,
            'current_ids' => $currentIds,
            'added_ids' => $addedIds,
            'removed_ids' => $removedIds,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function normalizeIds(array|Collection $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, TaskAssignment>
     */
    private function latestAssignmentsByUser(Task $task): Collection
    {
        return $task->assignments()
            ->whereNotNull('assigned_to_user_id')
            ->orderByDesc('id')
            ->get()
            ->unique('assigned_to_user_id')
            ->keyBy(fn (TaskAssignment $assignment): int => (int) $assignment->assigned_to_user_id);
    }

    private function refreshPrimaryAssignee(Task $task, User $actor): void
    {
        $latestAcceptedAssignment = $task->assignments()
            ->where('status', TaskAssignmentStatus::ACCEPTED->value)
            ->latest('id')
            ->first();

        $primaryAssigneeId = $latestAcceptedAssignment?->assigned_to_user_id;

        if (! $primaryAssigneeId && in_array($task->status instanceof TaskStatus ? $task->status : TaskStatus::from((string) $task->status), [
            TaskStatus::AWAITING_REPORTER_CONFIRMATION,
            TaskStatus::COMPLETED,
        ], true)) {
            $primaryAssigneeId = $task->assignments()
                ->where('status', TaskAssignmentStatus::COMPLETED->value)
                ->latest('id')
                ->value('assigned_to_user_id');
        }

        $task->forceFill([
            'assigned_to_user_id' => $primaryAssigneeId ?: null,
            'updated_by' => $actor->id,
        ])->save();
    }
}
