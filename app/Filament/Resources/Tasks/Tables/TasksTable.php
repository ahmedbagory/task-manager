<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Tasks\TaskAccessService;
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
                    ->label('رقم المهمة')
                    ->formatStateUsing(fn ($state, Task $record): string => $record->displayNumber())
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('title')
                    ->label('عنوان المهمة')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn ($state, Task $record): string => $record->workflowStatusLabel())
                    ->color(fn ($state, Task $record): string => $record->workflowStatus()->color()),
                TextColumn::make('priority')
                    ->label('الأولوية')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->label())
                    ->color(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->color()),
                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->label())
                    ->color(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->color()),
                TextColumn::make('department.hierarchy_name')
                    ->label('القسم / الوحدة')
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('assignees_summary')
                    ->label('المكلفين')
                    ->state(fn (Task $record): string => app(TaskAccessService::class)
                        ->resolveAssignees($record)
                        ->pluck('name')
                        ->implode('، ') ?: '—')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('assignedToUser', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('assignments.assignedToUser', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->wrap()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('reporter_summary')
                    ->label('صاحب الطلب')
                    ->state(function (Task $record): string {
                        $reporter = app(TaskAccessService::class)->resolveReporter($record);

                        if (! $reporter) {
                            return '—';
                        }

                        $name = $reporter['name'] ?? null;
                        $phone = $reporter['phone'] ?? null;

                        return trim(implode(' - ', array_filter([$name, $phone]))) ?: '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('reportedByUser', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('whatsappContact', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                            ->orWhere('reported_by_phone', 'like', "%{$search}%");
                    })
                    ->wrap()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('assigned_by_summary')
                    ->label('تم الإسناد بواسطة')
                    ->state(fn (Task $record): string => $record->latestAssignment?->assignedByUser?->name
                        ?: $record->createdByUser?->name
                        ?: '—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('due_at')
                    ->label('تاريخ الاستحقاق')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('description')
                    ->label('الوصف')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(60)
                    ->placeholder('-'),
                TextColumn::make('reported_by_phone')
                    ->label('هاتف المبلّغ')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('location')
                    ->label('الموقع')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('تاريخ الحذف')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(TaskStatus::options()),
                SelectFilter::make('priority')
                    ->label('الأولوية')
                    ->options(TaskPriority::options()),
                SelectFilter::make('source')
                    ->label('المصدر')
                    ->options(TaskSource::options()),
                SelectFilter::make('department_id')
                    ->label('القسم / الوحدة')
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions()),
                SelectFilter::make('assigned_to_user_id')
                    ->label('الموظف المكلف')
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
