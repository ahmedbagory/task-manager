<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentService;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getAssignEmployeeAction(),
            EditAction::make(),
        ];
    }

    protected function getAssignEmployeeAction(): Action
    {
        return Action::make('assignEmployee')
            ->label(__('Assign Employee'))
            ->icon('heroicon-o-user-plus')
            ->authorize(fn (): bool => auth()->user()?->can('assign', $this->record) ?? false)
            ->form([
                Select::make('assigned_to_user_id')
                    ->label(__('Employee'))
                    ->options(fn (): array => User::query()
                        ->role([Rbac::EMPLOYEE, Rbac::SUPERVISOR])
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('note')
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

                $this->record = $task->fresh();

                Notification::make()
                    ->title(__('Task assigned successfully.'))
                    ->success()
                    ->send();
            })
            ->modalSubmitActionLabel(__('Assign'))
            ->modalWidth('lg');
    }
}
