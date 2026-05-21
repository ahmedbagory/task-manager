<?php

namespace App\Filament\Resources\TaskReports;

use App\Filament\Resources\TaskReports\Pages\ListTaskReports;
use App\Filament\Resources\TaskReports\Tables\TaskReportsTable;
use App\Models\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaskReportResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return TaskReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskReports::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Task Report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Task Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('Task Reports');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Reports');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('reports.view') ?? false;
    }
}
