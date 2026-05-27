<?php

namespace App\Filament\Dashboard;

use App\Filament\Dashboard\Widgets\DashboardTaskOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class DashboardPage extends BaseDashboard
{
    protected string $view = 'filament.dashboard.dashboard-page';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            DashboardTaskOverviewWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
