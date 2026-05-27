<?php

namespace App\Filament\Dashboard\Widgets;

use App\Models\Task;
use App\Services\Reports\TaskReportService;
use Filament\Widgets\Widget;

class DashboardTaskOverviewWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.dashboard.widgets.task-overview-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $overview = app(TaskReportService::class)->getOverview(Task::query());

        return [
            'stats' => [
                [
                    'label' => __('Total Tasks'),
                    'value' => number_format($overview['total_tasks']),
                    'value_color' => 'text-gray-950 dark:text-white',
                ],
                [
                    'label' => __('New Tasks'),
                    'value' => number_format($overview['new_tasks']),
                    'value_color' => 'text-sky-700 dark:text-sky-300',
                ],
                [
                    'label' => __('Pending Assignment'),
                    'value' => number_format($overview['pending_assignment_tasks']),
                    'value_color' => 'text-amber-700 dark:text-amber-300',
                ],
                [
                    'label' => __('In Progress'),
                    'value' => number_format($overview['in_progress_tasks']),
                    'value_color' => 'text-blue-700 dark:text-blue-300',
                ],
                [
                    'label' => __('Completed'),
                    'value' => number_format($overview['completed_tasks']),
                    'value_color' => 'text-emerald-700 dark:text-emerald-300',
                ],
                [
                    'label' => __('Overdue Tasks'),
                    'value' => number_format($overview['overdue_tasks']),
                    'value_color' => 'text-rose-700 dark:text-rose-300',
                ],
            ],
        ];
    }
}
