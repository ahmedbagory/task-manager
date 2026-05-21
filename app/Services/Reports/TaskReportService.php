<?php

namespace App\Services\Reports;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\WhatsappMessageDirection;
use App\Models\Task;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskReportService
{
    /**
     * @return array<string, int|float|null>
     */
    public function getOverview(EloquentBuilder $filteredTaskQuery): array
    {
        $filteredTaskIds = $this->filteredTaskIdsSubquery($filteredTaskQuery);

        $baseQuery = Task::query()
            ->whereIn('tasks.id', $filteredTaskIds);

        $durationExpression = $this->completionDurationExpression();

        $overview = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_tasks')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as new_tasks', [TaskStatus::NEW->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_assignment_tasks', [TaskStatus::PENDING_ASSIGNMENT->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted_tasks', [TaskStatus::ACCEPTED->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_tasks', [TaskStatus::IN_PROGRESS->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as wait_response_tasks', [TaskStatus::WAIT_RESPONSE->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_tasks', [TaskStatus::COMPLETED->value])
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as rejected_cancelled_tasks',
                [TaskStatus::REJECTED->value, TaskStatus::CANCELLED->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_at IS NOT NULL AND due_at < ? AND status NOT IN (?, ?, ?) THEN 1 ELSE 0 END) as overdue_tasks',
                [now(), TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value, TaskStatus::REJECTED->value]
            )
            ->selectRaw("AVG({$durationExpression}) as average_completion_seconds")
            ->first();

        $convertedToTasks = WhatsappMessage::query()
            ->where('direction', WhatsappMessageDirection::INBOUND->value)
            ->whereNotNull('task_id')
            ->whereIn('task_id', $filteredTaskIds)
            ->count();

        $avgSeconds = $overview?->average_completion_seconds;
        $avgSeconds = is_numeric($avgSeconds) ? (float) $avgSeconds : null;

        return [
            'total_tasks' => (int) ($overview?->total_tasks ?? 0),
            'new_tasks' => (int) ($overview?->new_tasks ?? 0),
            'pending_assignment_tasks' => (int) ($overview?->pending_assignment_tasks ?? 0),
            'accepted_tasks' => (int) ($overview?->accepted_tasks ?? 0),
            'in_progress_tasks' => (int) ($overview?->in_progress_tasks ?? 0),
            'wait_response_tasks' => (int) ($overview?->wait_response_tasks ?? 0),
            'completed_tasks' => (int) ($overview?->completed_tasks ?? 0),
            'rejected_cancelled_tasks' => (int) ($overview?->rejected_cancelled_tasks ?? 0),
            'overdue_tasks' => (int) ($overview?->overdue_tasks ?? 0),
            'whatsapp_converted_tasks' => (int) $convertedToTasks,
            'average_completion_seconds' => $avgSeconds,
        ];
    }

    /**
     * @return array<string, Collection<int, array{label: string, total: int}>>
     */
    public function getBreakdowns(EloquentBuilder $filteredTaskQuery, int $limit = 10): array
    {
        $filteredTaskIds = $this->filteredTaskIdsSubquery($filteredTaskQuery);

        $byDepartment = Task::query()
            ->leftJoin('departments', 'departments.id', '=', 'tasks.department_id')
            ->whereIn('tasks.id', $filteredTaskIds)
            ->selectRaw('departments.name as label, COUNT(*) as total')
            ->groupBy('tasks.department_id', 'departments.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) ($row->label ?: __('Unassigned')),
                'total' => (int) $row->total,
            ])
            ->values();

        $byCategory = Task::query()
            ->leftJoin('task_categories', 'task_categories.id', '=', 'tasks.category_id')
            ->whereIn('tasks.id', $filteredTaskIds)
            ->selectRaw('task_categories.name as label, COUNT(*) as total')
            ->groupBy('tasks.category_id', 'task_categories.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) ($row->label ?: __('Uncategorized')),
                'total' => (int) $row->total,
            ])
            ->values();

        $byEmployee = Task::query()
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to_user_id')
            ->whereIn('tasks.id', $filteredTaskIds)
            ->selectRaw('users.name as label, COUNT(*) as total')
            ->groupBy('tasks.assigned_to_user_id', 'users.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) ($row->label ?: __('Unassigned')),
                'total' => (int) $row->total,
            ])
            ->values();

        $byPriority = Task::query()
            ->whereIn('tasks.id', $filteredTaskIds)
            ->selectRaw('priority as label, COUNT(*) as total')
            ->groupBy('priority')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'label' => TaskPriority::from((string) $row->label)->label(),
                'total' => (int) $row->total,
            ])
            ->values();

        $byStatus = Task::query()
            ->whereIn('tasks.id', $filteredTaskIds)
            ->selectRaw('status as label, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'label' => TaskStatus::from((string) $row->label)->label(),
                'total' => (int) $row->total,
            ])
            ->values();

        $bySource = Task::query()
            ->whereIn('tasks.id', $filteredTaskIds)
            ->selectRaw('source as label, COUNT(*) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'label' => match ((string) $row->label) {
                    TaskSource::WHATSAPP->value => TaskSource::WHATSAPP->label(),
                    TaskSource::API->value => TaskSource::API->label(),
                    default => TaskSource::MANUAL->label(),
                },
                'total' => (int) $row->total,
            ])
            ->values();

        return [
            'by_department' => $byDepartment,
            'by_category' => $byCategory,
            'by_employee' => $byEmployee,
            'by_priority' => $byPriority,
            'by_status' => $byStatus,
            'by_source' => $bySource,
        ];
    }

    private function filteredTaskIdsSubquery(EloquentBuilder $filteredTaskQuery): QueryBuilder
    {
        $query = clone $filteredTaskQuery;

        return $query
            ->reorder()
            ->select('tasks.id')
            ->toBase();
    }

    private function completionDurationExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CASE WHEN completed_at IS NOT NULL THEN (strftime('%s', completed_at) - strftime('%s', created_at)) END";
        }

        if ($driver === 'pgsql') {
            return 'CASE WHEN completed_at IS NOT NULL THEN EXTRACT(EPOCH FROM (completed_at - created_at)) END';
        }

        return 'CASE WHEN completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) END';
    }
}
