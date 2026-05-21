<?php

namespace App\Filament\Resources\TaskReports\Widgets;

use App\Filament\Resources\TaskReports\Pages\ListTaskReports;
use App\Services\Reports\TaskReportService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;

class TaskReportsBreakdownsWidget extends Widget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.resources.task-reports.widgets.task-reports-breakdowns-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListTaskReports::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'breakdowns' => app(TaskReportService::class)->getBreakdowns($this->getPageTableQuery()),
        ];
    }
}
