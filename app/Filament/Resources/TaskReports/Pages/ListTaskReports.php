<?php

namespace App\Filament\Resources\TaskReports\Pages;

use App\Filament\Resources\TaskReports\TaskReportResource;
use App\Filament\Resources\TaskReports\Widgets\TaskReportsBreakdownsWidget;
use App\Filament\Resources\TaskReports\Widgets\TaskReportsOverviewWidget;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListTaskReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TaskReportResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TaskReportsOverviewWidget::class,
            TaskReportsBreakdownsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 1,
        ];
    }
}
