<?php

namespace App\Filament\Resources\TaskReports\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Support\Rbac;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskReportsTable
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
                    ->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TaskStatus|string $state): string => ($state instanceof TaskStatus ? $state : TaskStatus::from((string) $state))->label())
                    ->color(fn (TaskStatus|string $state): string => ($state instanceof TaskStatus ? $state : TaskStatus::from((string) $state))->color()),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->label())
                    ->color(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->color()),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->label())
                    ->color(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->color()),
                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('assignedToUser.name')
                    ->label(__('Employee'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_at')
                    ->label(__('Due Date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label(__('Completed At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at_range')
                    ->label(__('Date Range'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('From')),
                        DatePicker::make('until')
                            ->label(__('To')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $builder): Builder => $builder->whereDate('tasks.created_at', '>=', (string) $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $builder): Builder => $builder->whereDate('tasks.created_at', '<=', (string) $data['until']),
                            );
                    }),
                SelectFilter::make('department_id')
                    ->label(__('Department'))
                    ->relationship('department', 'name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('assigned_to_user_id')
                    ->label(__('Employee'))
                    ->relationship(
                        name: 'assignedToUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', [
                                Rbac::EMPLOYEE,
                                Rbac::SUPERVISOR,
                            ])),
                    )
                    ->preload()
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(TaskStatus::options()),
                SelectFilter::make('priority')
                    ->options(TaskPriority::options()),
                SelectFilter::make('source')
                    ->options(TaskSource::options()),
            ])
            ->defaultSort('tasks.created_at', 'desc')
            ->recordUrl(fn ($record): string => TaskResource::getUrl('view', ['record' => $record]))
            ->recordAction(null)
            ->paginated([10, 25, 50, 100]);
    }
}
