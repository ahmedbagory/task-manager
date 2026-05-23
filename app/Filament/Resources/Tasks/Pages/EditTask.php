<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use App\Services\Tasks\TaskService;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getAssignAction(),
            $this->getReassignAction(),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge(
            $data,
            app(TaskAssignmentTargetResolver::class)->fillFormTargets($this->record),
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(TaskService::class)->updateTask($record, $data, $user);
    }

    protected function getAssignAction(): Action
    {
        return Action::make('assign')
            ->label(__('تعيين'))
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (): bool => $this->canShowAssignAction())
            ->form($this->buildAssignFormFields())
            ->fillForm(fn (): array => app(TaskAssignmentTargetResolver::class)->fillFormTargets($this->record))
            ->action(function (array $data): void {
                /** @var User $actor */
                $actor = auth()->user();

                app(TaskAssignmentTargetResolver::class)->syncTargets($this->record, [
                    'all' => ! empty($data['assign_to_all']),
                    'departments' => array_map('intval', (array) ($data['assignment_target_departments'] ?? [])),
                    'units' => array_map('intval', (array) ($data['assignment_target_units'] ?? [])),
                    'users' => array_map('intval', (array) ($data['assignment_target_users'] ?? [])),
                ], $actor);

                TaskAssignmentHistory::query()->create([
                    'task_id' => $this->record->id,
                    'action' => 'targets_updated',
                    'performed_by' => $actor->id,
                    'note' => $data['note'] ?? null,
                ]);

                $this->record = $this->record->fresh();

                Notification::make()
                    ->title(__('تم تحديث الإسناد بنجاح'))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('إسناد المهمة'))
            ->modalSubmitActionLabel(__('حفظ الإسناد'))
            ->modalWidth('lg');
    }

    protected function getReassignAction(): Action
    {
        return Action::make('reassign')
            ->label(__('إعادة التعيين'))
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (): bool => $this->canShowReassignAction())
            ->requiresConfirmation()
            ->modalHeading(__('إعادة تعيين المهمة'))
            ->modalDescription(__('سيتم إلغاء أي تعيين نشط حالي وتحديث الإسناد الجديد.'))
            ->form($this->buildAssignFormFields())
            ->action(function (array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                /** @var Task $task */
                $task = $this->record;
                $previousUserId = $task->assigned_to_user_id;

                $task->assignments()
                    ->whereIn('status', [TaskAssignmentStatus::ASSIGNED->value, TaskAssignmentStatus::ACCEPTED->value])
                    ->each(function ($a) {
                        $a->forceFill(['status' => TaskAssignmentStatus::REJECTED, 'note' => __('إعادة تعيين')])->save();
                    });

                $task->forceFill([
                    'assigned_to_user_id' => null,
                    'status' => TaskStatus::PENDING_ASSIGNMENT->value,
                    'updated_by' => $actor->id,
                ])->save();

                app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
                    'all' => ! empty($data['assign_to_all']),
                    'departments' => array_map('intval', (array) ($data['assignment_target_departments'] ?? [])),
                    'units' => array_map('intval', (array) ($data['assignment_target_units'] ?? [])),
                    'users' => array_map('intval', (array) ($data['assignment_target_users'] ?? [])),
                ], $actor);

                TaskAssignmentHistory::query()->create([
                    'task_id' => $task->id,
                    'action' => 'reassigned',
                    'from_user_id' => $previousUserId,
                    'performed_by' => $actor->id,
                    'note' => $data['reason'] ?? null,
                ]);

                $this->record = $task->fresh();

                Notification::make()
                    ->title(__('تمت إعادة التعيين بنجاح'))
                    ->success()
                    ->send();
            })
            ->modalSubmitActionLabel(__('إعادة التعيين'))
            ->modalWidth('lg');
    }

    private function canShowAssignAction(): bool
    {
        return (auth()->user()?->can('assign', $this->record) ?? false)
            && ! in_array($this->record->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true);
    }

    private function canShowReassignAction(): bool
    {
        return (auth()->user()?->can('tasks.reassign') ?? false)
            && ! in_array($this->record->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true)
            && ($this->record->hasActiveAssignee() || $this->record->hasValidAssignmentTargets());
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function buildAssignFormFields(): array
    {
        return [
            Toggle::make('assign_to_all')
                ->label(__('إسناد للكل'))
                ->helperText(__('عند التفعيل سيتم إسناد المهمة لجميع الموظفين.'))
                ->live()
                ->afterStateUpdated(function (Set $set, $state): void {
                    if ($state) {
                        $set('assignment_target_departments', []);
                        $set('assignment_target_units', []);
                        $set('assignment_target_users', []);
                    }
                }),
            Select::make('assignment_target_departments')
                ->label(__('الأقسام'))
                ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('assignment_target_units', []))
                ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all')),
            Select::make('assignment_target_units')
                ->label(__('الفروع'))
                ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                    parentIds: (array) ($get('assignment_target_departments') ?? []),
                ))
                ->multiple()
                ->searchable()
                ->preload()
                ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all')),
            Select::make('assignment_target_users')
                ->label(__('موظفين محددين'))
                ->options(fn (): array => User::query()
                    ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [
                        Rbac::EMPLOYEE,
                        Rbac::SUPERVISOR,
                    ]))
                    ->with('department.parent')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (User $u) => [
                        $u->id => $u->name . ($u->department ? ' (' . $u->department->hierarchy_name . ')' : ''),
                    ])
                    ->all())
                ->multiple()
                ->searchable()
                ->preload()
                ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all')),
            Textarea::make('note')
                ->label(__('ملاحظة'))
                ->rows(3)
                ->maxLength(1000),
        ];
    }
}
