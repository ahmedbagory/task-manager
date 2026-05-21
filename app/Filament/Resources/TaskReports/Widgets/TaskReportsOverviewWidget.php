<?php

namespace App\Filament\Resources\TaskReports\Widgets;

use App\Filament\Resources\TaskReports\Pages\ListTaskReports;
use App\Services\Reports\TaskReportService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskReportsOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('Task Performance Overview');
    }

    protected function getTablePage(): string
    {
        return ListTaskReports::class;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $overview = app(TaskReportService::class)->getOverview($this->getPageTableQuery());

        return [
            Stat::make(__('Total Tasks'), (string) $overview['total_tasks'])
                ->color('gray'),
            Stat::make(__('New Tasks'), (string) $overview['new_tasks'])
                ->color('info'),
            Stat::make(__('Pending Assignment'), (string) $overview['pending_assignment_tasks'])
                ->color('warning'),
            Stat::make(__('Accepted'), (string) $overview['accepted_tasks'])
                ->color('primary'),
            Stat::make(__('In Progress'), (string) $overview['in_progress_tasks'])
                ->color('primary'),
            Stat::make(__('Waiting Response'), (string) $overview['wait_response_tasks'])
                ->color('warning'),
            Stat::make(__('Completed'), (string) $overview['completed_tasks'])
                ->color('success'),
            Stat::make(__('Rejected / Cancelled'), (string) $overview['rejected_cancelled_tasks'])
                ->color('danger'),
            Stat::make(__('Overdue Tasks'), (string) $overview['overdue_tasks'])
                ->color('danger'),
            Stat::make(__('WA Converted To Tasks'), (string) $overview['whatsapp_converted_tasks'])
                ->color('success'),
            Stat::make(__('Avg Completion Time'), $this->formatDuration($overview['average_completion_seconds']))
                ->color('gray'),
        ];
    }

    private function formatDuration(float|int|null $seconds): string
    {
        if (! is_numeric($seconds) || $seconds <= 0) {
            return '-';
        }

        $seconds = (int) round((float) $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv(($seconds % 3600), 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        if ($minutes > 0) {
            return "{$minutes}m";
        }

        return "{$seconds}s";
    }
}
