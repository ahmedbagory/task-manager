<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Tasks\TaskAssignmentService;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getAssignEmployeeAction(),
            $this->getAssignTargetsAction(),
            $this->getReassignAction(),
            EditAction::make(),
        ];
    }

    protected function getAssignEmployeeAction(): Action
    {
        return Action::make('assignEmployee')
            ->label(__('تعيين موظف'))
            ->icon('heroicon-o-user-plus')
            ->authorize(fn (): bool => auth()->user()?->can('assign', $this->record) ?? false)
            ->form([
                Select::make('assigned_to_user_id')
                    ->label(__('الموظف'))
                    ->options(fn (): array => app(TaskAssignmentTargetResolver::class)->assignmentOptionsForTask($this->record))
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('note')
                    ->label(__('ملاحظة'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, TaskAssignmentService $taskAssignmentService): void {
                /** @var Task $task */
                $task = $this->record;

                /** @var User $assignedBy */
                $assignedBy = auth()->user();

                $taskAssignmentService->assignTask(
                    task: $task,
                    assignedToUserId: (int) $data['assigned_to_user_id'],
                    assignedBy: $assignedBy,
                    note: $data['note'] ?? null,
                );

                TaskAssignmentHistory::query()->create([
                    'task_id' => $task->id,
                    'action' => 'assigned',
                    'to_user_id' => (int) $data['assigned_to_user_id'],
                    'performed_by' => $assignedBy->id,
                    'note' => $data['note'] ?? null,
                ]);

                $this->record = $task->fresh();

                Notification::make()
                    ->title(__('تم تعيين المهمة بنجاح'))
                    ->success()
                    ->send();
            })
            ->modalSubmitActionLabel(__('تعيين'))
            ->modalWidth('lg');
    }

    protected function getAssignTargetsAction(): Action
    {
        return Action::make('assignTargets')
            ->label(__('توجيه المهمة'))
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->authorize(fn (): bool => auth()->user()?->can('tasks.assign') ?? false)
            ->form([
                Select::make('assignment_target_departments')
                    ->label('الأقسام الرئيسية')
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('assignment_target_units')
                    ->label('الوحدات / الفروع')
                    ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                        parentIds: (array) ($get('assignment_target_departments') ?? []),
                    ))
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Select::make('assignment_target_users')
                    ->label(__('موظفين'))
                    ->options(fn (): array => app(TaskAssignmentTargetResolver::class)->assignmentOptionsForTask())
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->fillForm(fn (): array => app(TaskAssignmentTargetResolver::class)->fillFormTargets($this->record))
            ->action(function (array $data, TaskAssignmentTargetResolver $resolver): void {
                /** @var Task $task */
                $task = $this->record;
                $assignedBy = auth()->user();

                $resolver->syncTargets($task, [
                    'departments' => array_map('intval', (array) ($data['assignment_target_departments'] ?? [])),
                    'units' => array_map('intval', (array) ($data['assignment_target_units'] ?? [])),
                    'users' => array_map('intval', (array) ($data['assignment_target_users'] ?? [])),
                ], $assignedBy);

                $resolvedUsers = $resolver->resolveUsersForTask($task);

                $this->record = $task->fresh();

                Notification::make()
                    ->title(__('تم تحديث التوجيه بنجاح'))
                    ->body(__('عدد الموظفين المطابقين: :count', ['count' => $resolvedUsers->count()]))
                    ->success()
                    ->send();
            })
            ->modalSubmitActionLabel(__('توجيه'))
            ->modalWidth('lg');
    }

    protected function getReassignAction(): Action
    {
        return Action::make('reassign')
            ->label(__('إعادة تعيين'))
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()?->can('tasks.reassign') ?? false)
            ->visible(fn (): bool => $this->record->assigned_to_user_id !== null)
            ->form([
                Select::make('new_user_id')
                    ->label(__('الموظف الجديد'))
                    ->options(fn (): array => app(TaskAssignmentTargetResolver::class)->assignmentOptionsForTask(
                        task: $this->record,
                        excludeUserId: $this->record->assigned_to_user_id,
                    ))
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('reason')
                    ->label(__('سبب إعادة التعيين'))
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, TaskAssignmentService $taskAssignmentService): void {
                /** @var Task $task */
                $task = $this->record;

                /** @var User $performer */
                $performer = auth()->user();

                $previousUserId = $task->assigned_to_user_id;

                $task->assignments()
                    ->whereIn('status', ['assigned', 'accepted'])
                    ->update(['status' => 'rejected', 'note' => 'إعادة تعيين: ' . $data['reason']]);

                $taskAssignmentService->assignTask(
                    task: $task->fresh(),
                    assignedToUserId: (int) $data['new_user_id'],
                    assignedBy: $performer,
                    note: $data['reason'],
                );

                TaskAssignmentHistory::query()->create([
                    'task_id' => $task->id,
                    'action' => 'reassigned',
                    'from_user_id' => $previousUserId,
                    'to_user_id' => (int) $data['new_user_id'],
                    'performed_by' => $performer->id,
                    'note' => $data['reason'],
                ]);

                $this->record = $task->fresh();

                Notification::make()
                    ->title(__('تم إعادة التعيين بنجاح'))
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading(__('إعادة تعيين المهمة'))
            ->modalDescription(__('سيتم إلغاء التعيين الحالي وتعيين الموظف الجديد'))
            ->modalSubmitActionLabel(__('إعادة تعيين'))
            ->modalWidth('lg');
    }
}
