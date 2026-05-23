<?php

namespace App\Console\Commands;

use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupLegacyAllTaskAssignmentTargets extends Command
{
    protected $signature = 'tasks:cleanup-legacy-all-targets {--dry-run : Preview the conversion without writing changes}';

    protected $description = 'Convert legacy task assignment targets that store target_type=all into concrete user target rows.';

    public function handle(TaskAssignmentTargetResolver $resolver): int
    {
        $legacyTargets = TaskAssignmentTarget::query()
            ->where('target_type', TaskAssignmentTarget::LEGACY_ALL)
            ->orderBy('task_id')
            ->get();

        if ($legacyTargets->isEmpty()) {
            $this->info('No legacy task assignment targets with target_type=all were found.');

            return self::SUCCESS;
        }

        $eligibleUserIds = $resolver->resolveAllEligibleUsers()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($eligibleUserIds === []) {
            $this->warn('No eligible users were found for conversion. Nothing was changed.');

            return self::INVALID;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $groupedByTask = $legacyTargets->groupBy('task_id');
        $convertedTasks = 0;
        $insertedRows = 0;
        $deletedRows = 0;

        foreach ($groupedByTask as $taskId => $taskTargets) {
            $existingUserIds = TaskAssignmentTarget::query()
                ->where('task_id', $taskId)
                ->whereIn('target_type', TaskAssignmentTarget::userTargetTypes())
                ->pluck('target_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $missingUserIds = array_values(array_diff($eligibleUserIds, $existingUserIds));
            $legacyRowCount = $taskTargets->count();
            $convertedTasks++;
            $insertedRows += count($missingUserIds);
            $deletedRows += $legacyRowCount;

            if ($isDryRun) {
                $this->line(sprintf(
                    'Task #%d: would insert %d user targets and delete %d legacy all row(s).',
                    $taskId,
                    count($missingUserIds),
                    $legacyRowCount,
                ));

                continue;
            }

            $referenceTarget = $taskTargets->first();

            DB::transaction(function () use ($taskId, $missingUserIds, $referenceTarget): void {
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

        $summary = sprintf(
            '%s %d task(s), %d inserted user target row(s), %d removed legacy all row(s).',
            $isDryRun ? 'Previewed' : 'Converted',
            $convertedTasks,
            $insertedRows,
            $deletedRows,
        );

        $this->info($summary);

        return self::SUCCESS;
    }
}
