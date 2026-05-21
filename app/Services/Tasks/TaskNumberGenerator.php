<?php

namespace App\Services\Tasks;

use App\Models\Task;

class TaskNumberGenerator
{
    public function generate(): string
    {
        $prefix = 'TASK-'.now()->format('Ymd');

        $latestTaskNumber = Task::query()
            ->withTrashed()
            ->where('task_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('task_number');

        $nextSequence = 1;

        if (filled($latestTaskNumber) && preg_match('/-(\d+)$/', $latestTaskNumber, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        do {
            $taskNumber = sprintf('%s-%04d', $prefix, $nextSequence++);
        } while (Task::query()->withTrashed()->where('task_number', $taskNumber)->exists());

        return $taskNumber;
    }
}
