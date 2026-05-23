<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Departments\DepartmentHierarchyService;
use App\Support\Rbac;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('task_number')
                    ->label(__('Task #'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state, Task $record): string => $record->workflowStatusLabel())
                    ->color(fn ($state, Task $record): string => $record->workflowStatus()->color()),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->label())
                    ->color(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->color()),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->label())
                    ->color(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->color()),
                TextColumn::make('department.hierarchy_name')
                    ->label('القسم / الوحدة')
                    ->placeholder('-'),
                TextColumn::make('assignedToUser.name')
                    ->label(__('Assigned Employee'))
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->assignedToUser?->department?->hierarchy_name)
                    ->placeholder('-'),
                TextColumn::make('due_at')
                    ->label(__('Due At'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(60)
                    ->placeholder('-'),
                TextColumn::make('reported_by_phone')
                    ->label(__('Reported By Phone'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('location')
                    ->label(__('Location'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('Deleted At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TaskStatus::options()),
                SelectFilter::make('priority')
                    ->options(TaskPriority::options()),
                SelectFilter::make('source')
                    ->options(TaskSource::options()),
                SelectFilter::make('department_id')
                    ->label('القسم / الوحدة')
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions()),
                SelectFilter::make('assigned_to_user_id')
                    ->label(__('Assigned Employee'))
                    ->relationship(
                        name: 'assignedToUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('name', [
                            Rbac::EMPLOYEE,
                            Rbac::SUPERVISOR,
                        ])),
                    ),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
