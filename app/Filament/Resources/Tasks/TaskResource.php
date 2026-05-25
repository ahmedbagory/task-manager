<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\RelationManagers\AssignmentHistoryRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use App\Services\Tasks\TaskAccessService;
use App\Support\Rbac;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
            AssignmentHistoryRelationManager::class,
            CommentsRelationManager::class,
            AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'مهمة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المهام';
    }

    public static function getNavigationLabel(): string
    {
        return 'المهام';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'إدارة المهام';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'reportedByUser',
            'whatsappContact.user',
            'createdByUser',
            'assignedToUser.department.parent',
            'latestAssignment.assignedByUser',
            'assignments.assignedToUser.department.parent',
            'assignmentTargets',
        ]);
        $user = Auth::user();

        if ($user?->hasRole(Rbac::EMPLOYEE)) {
            app(TaskAccessService::class)->applyVisibleToUserScope($query, $user);
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
