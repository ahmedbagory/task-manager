<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairTaskAssignmentStates extends Command
{
    protected $signature = 'tasks:repair-assignment-states {--dry-run : Preview the repair without writing changes}';

    protected $description = 'Repair inconsistent task assignment statuses and convert legacy all-target rows.';

    public function handle(TaskAssignmentTargetResolver $resolver): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $eligibleUserIds = $resolver->resolveAllEligibleUsers()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $invalidTargetRowsFixed = 0;
        $fixedToPendingAcceptance = 0;
        $fixedToUnassigned = 0;

        $legacyTargets = TaskAssignmentTarget::query()
            ->where('target_type', TaskAssignmentTarget::LEGACY_ALL)
            ->orderBy('task_id')
            ->get()
            ->groupBy('task_id');

        foreach ($legacyTargets as $taskId => $taskTargets) {
            $referenceTarget = $taskTargets->first();
            $legacyRowCount = $taskTargets->count();
            $invalidTargetRowsFixed += $legacyRowCount;

            if ($isDryRun) {
                $this->line(sprintf(
                    'Task #%d: would convert %d legacy all row(s) into concrete user targets.',
                    $taskId,
                    $legacyRowCount,
                ));

                continue;
            }

            DB::transaction(function () use ($taskId, $referenceTarget, $eligibleUserIds): void {
                $existingUserIds = TaskAssignmentTarget::query()
                    ->where('task_id', $taskId)
                    ->whereIn('target_type', TaskAssignmentTarget::userTargetTypes())
                    ->pluck('target_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                $missingUserIds = array_values(array_diff($eligibleUserIds, $existingUserIds));

                foreach ($missingUserIds as $userId) {
                    TaskAssignmentTarget::query()->create([
                        'task_id' => (int) $taskId,
                        'target_type' => User::class,
                        'target_id' => $userId,
                        'assigned_by' => $referenceTarget?->assigned_by,
                    ]);
                }

                TaskAssignmentTarget::query()
                    ->where('task_id', $taskId)
                    ->where('target_type', TaskAssignmentTarget::LEGACY_ALL)
                    ->delete();
            });
        }

        $tasks = Task::query()
            ->with('assignmentTargets')
            ->orderBy('id')
            ->get();

        foreach ($tasks as $task) {
            $rawStatus = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::from((string) $task->status);

            if (in_array($rawStatus, [
                TaskStatus::ACCEPTED,
                TaskStatus::IN_PROGRESS,
                TaskStatus::WAIT_RESPONSE,
                TaskStatus::COMPLETED,
                TaskStatus::CANCELLED,
            ], true)) {
                continue;
            }

            $hasActiveAssignee = $task->hasActiveAssignee();
            $hasValidTargets = $task->hasValidAssignmentTargets();

            if (! $hasActiveAssignee && $hasValidTargets && in_array($rawStatus, [
                TaskStatus::NEW,
                TaskStatus::PENDING_ASSIGNMENT,
                TaskStatus::REJECTED,
            ], true)) {
                $fixedToPendingAcceptance++;

                if (! $isDryRun) {
                    $task->forceFill([
                        'status' => TaskStatus::ASSIGNED->value,
                    ])->save();
                }

                continue;
            }

            if (! $hasActiveAssignee && (! $hasValidTargets) && $rawStatus === TaskStatus::ASSIGNED) {
                $fixedToUnassigned++;

                if (! $isDryRun) {
                    $task->forceFill([
                        'status' => TaskStatus::PENDING_ASSIGNMENT->value,
                    ])->save();
                }
            }
        }

        $this->info(sprintf(
            '%s tasks fixed from unassigned to pending acceptance: %d',
            $isDryRun ? 'Would be' : 'Total',
            $fixedToPendingAcceptance,
        ));
        $this->info(sprintf(
            '%s tasks fixed from assigned to unassigned: %d',
            $isDryRun ? 'Would be' : 'Total',
            $fixedToUnassigned,
        ));
        $this->info(sprintf(
            '%s invalid target rows removed/converted: %d',
            $isDryRun ? 'Would be' : 'Total',
            $invalidTargetRowsFixed,
        ));

        return self::SUCCESS;
    }
}
