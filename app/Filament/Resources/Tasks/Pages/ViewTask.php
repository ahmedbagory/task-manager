<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Tasks\TaskAssignmentService;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected string $view = 'filament.resources.tasks.pages.view-task';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->refreshTaskRecord();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getAssignEmployeeAction(),
            $this->getAssignTargetsAction(),
            $this->getReassignAction(),
            EditAction::make(),
        ];
    }

    public function getTask(): Task
    {
        /** @var Task $task */
        $task = $this->record;

        return $task;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAttachmentPreviewItems(): array
    {
        return $this->getTask()
            ->attachments
            ->sortByDesc('created_at')
            ->filter(fn (TaskAttachment $attachment): bool => $attachment->isImage() || $attachment->isVideo() || $attachment->mime_type === 'application/pdf')
            ->values()
            ->map(fn (TaskAttachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name ?: basename($attachment->path),
                'mime' => $attachment->mime_type,
                'preview_type' => $attachment->isImage() ? 'image' : ($attachment->isVideo() ? 'video' : 'pdf'),
                'preview_url' => route('attachments.preview', $attachment),
                'download_url' => route('attachments.download', $attachment),
                'size' => $attachment->humanSize(),
                'uploaded_by' => $attachment->user?->name ?? __('Unknown'),
                'uploaded_at' => $attachment->created_at?->format('Y-m-d H:i'),
            ])
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function getResolvedTargetUsers(): Collection
    {
        return app(TaskAssignmentTargetResolver::class)->resolveUsersForTask($this->getTask());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getActivityFeed(): Collection
    {
        $task = $this->getTask();

        $historyItems = $task->assignmentHistories->map(fn ($history): array => [
            'type' => 'history',
            'title' => $history->action_label,
            'meta' => implode(' • ', array_filter([
                $history->performedByUser?->name,
                $history->toUser?->name ? __('إلى :name', ['name' => $history->toUser->name]) : null,
            ])),
            'body' => $history->note,
            'at' => $history->created_at,
        ]);

        $attachmentItems = $task->attachments->map(fn (TaskAttachment $attachment): array => [
            'type' => 'attachment',
            'title' => __('تم رفع مرفق'),
            'meta' => $attachment->user?->name ?? __('Unknown'),
            'body' => $attachment->original_name ?: basename($attachment->path),
            'at' => $attachment->created_at,
        ]);

        return $historyItems
            ->concat($attachmentItems)
            ->sortByDesc('at')
            ->values();
    }

    public function hasActiveAssignment(): bool
    {
        return $this->getTask()
            ->assignments
            ->contains(fn (TaskAssignment $assignment): bool => in_array($assignment->status->value, ['assigned', 'accepted'], true));
    }

    public function canShowAssignEmployeeAction(): bool
    {
        $task = $this->getTask();

        return (auth()->user()?->can('assign', $task) ?? false)
            && ! in_array($task->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true)
            && $task->assigned_to_user_id === null
            && ! $this->hasActiveAssignment();
    }

    public function canShowAssignTargetsAction(): bool
    {
        $task = $this->getTask();

        return (auth()->user()?->can('assign', $task) ?? false)
            && ! in_array($task->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true);
    }

    public function canShowReassignAction(): bool
    {
        $task = $this->getTask();

        return (auth()->user()?->can('tasks.reassign') ?? false)
            && ! in_array($task->status->value, [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value], true)
            && $task->assigned_to_user_id !== null
            && $this->hasActiveAssignment();
    }

    protected function getAssignEmployeeAction(): Action
    {
        return Action::make('assignEmployee')
            ->label(__('تعيين'))
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (): bool => $this->canShowAssignEmployeeAction())
            ->form([
                Select::make('assigned_to_user_id')
                    ->label(__('الموظف'))
                    ->options(fn (): array => app(TaskAssignmentTargetResolver::class)->assignmentOptionsForTask($this->getTask()))
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('note')
                    ->label(__('ملاحظة'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, TaskAssignmentService $taskAssignmentService): void {
                /** @var User $assignedBy */
                $assignedBy = auth()->user();

                $taskAssignmentService->assignTask(
                    task: $this->getTask(),
                    assignedToUserId: (int) $data['assigned_to_user_id'],
                    assignedBy: $assignedBy,
                    note: $data['note'] ?? null,
                );

                $this->refreshTaskRecord();

                Notification::make()
                    ->title(__('تم تعيين المهمة بنجاح'))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('تعيين موظف'))
            ->modalSubmitActionLabel(__('تعيين'))
            ->modalWidth('lg');
    }

    protected function getAssignTargetsAction(): Action
    {
        return Action::make('assignTargets')
            ->label(__('توجيه'))
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (): bool => $this->canShowAssignTargetsAction())
            ->form([
                Select::make('assignment_target_departments')
                    ->label(__('الأقسام الرئيسية'))
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('assignment_target_units')
                    ->label(__('الوحدات / الفروع'))
                    ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                        parentIds: (array) ($get('assignment_target_departments') ?? []),
                    ))
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Select::make('assignment_target_users')
                    ->label(__('موظفون محددون'))
                    ->options(fn (): array => app(TaskAssignmentTargetResolver::class)->assignmentOptionsForTask())
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->fillForm(fn (): array => app(TaskAssignmentTargetResolver::class)->fillFormTargets($this->getTask()))
            ->action(function (array $data, TaskAssignmentTargetResolver $resolver): void {
                /** @var User $actor */
                $actor = auth()->user();

                $resolver->syncTargets($this->getTask(), [
                    'departments' => array_map('intval', (array) ($data['assignment_target_departments'] ?? [])),
                    'units' => array_map('intval', (array) ($data['assignment_target_units'] ?? [])),
                    'users' => array_map('intval', (array) ($data['assignment_target_users'] ?? [])),
                ], $actor);

                $this->refreshTaskRecord();

                Notification::make()
                    ->title(__('تم تحديث التوجيه بنجاح'))
                    ->body(__('عدد الموظفين المطابقين: :count', ['count' => $this->getResolvedTargetUsers()->count()]))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('توجيه المهمة'))
            ->modalSubmitActionLabel(__('حفظ التوجيه'))
            ->modalWidth('lg');
    }

    protected function getReassignAction(): Action
    {
        return Action::make('reassign')
            ->label(__('إعادة تعيين'))
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (): bool => $this->canShowReassignAction())
            ->form([
                Select::make('new_user_id')
                    ->label(__('الموظف الجديد'))
                    ->options(fn (): array => app(TaskAssignmentTargetResolver::class)->assignmentOptionsForTask(
                        task: $this->getTask(),
                        excludeUserId: $this->getTask()->assigned_to_user_id,
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
                /** @var User $actor */
                $actor = auth()->user();

                $taskAssignmentService->reassignTask(
                    task: $this->getTask(),
                    newUserId: (int) $data['new_user_id'],
                    actor: $actor,
                    reason: $data['reason'],
                );

                $this->refreshTaskRecord();

                Notification::make()
                    ->title(__('تمت إعادة التعيين بنجاح'))
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading(__('إعادة تعيين المهمة'))
            ->modalDescription(__('سيتم إنهاء التعيين النشط الحالي وإرسال المهمة للموظف الجديد.'))
            ->modalSubmitActionLabel(__('إعادة التعيين'))
            ->modalWidth('lg');
    }

    public function addCommentAction(): Action
    {
        return Action::make('addComment')
            ->label(__('إضافة تعليق'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('comment', $this->getTask()) ?? false)
            ->form([
                Textarea::make('comment')
                    ->label(__('التعليق'))
                    ->required()
                    ->rows(4)
                    ->maxLength(5000),
                Toggle::make('is_internal')
                    ->label(__('تعليق داخلي'))
                    ->default(false)
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(Rbac::EMPLOYEE) ?? false)),
            ])
            ->action(function (array $data): void {
                /** @var User $actor */
                $actor = auth()->user();

                $this->getTask()->comments()->create([
                    'user_id' => $actor->id,
                    'comment' => $data['comment'],
                    'is_internal' => (bool) ($data['is_internal'] ?? false),
                ]);

                $this->refreshTaskRecord();

                Notification::make()
                    ->title(__('تمت إضافة التعليق'))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('إضافة تعليق جديد'))
            ->modalSubmitActionLabel(__('إرسال التعليق'))
            ->modalWidth('lg');
    }

    public function uploadAttachmentAction(): Action
    {
        return Action::make('uploadAttachment')
            ->label(__('رفع مرفق'))
            ->icon('heroicon-o-paper-clip')
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('viewAttachments', $this->getTask()) ?? false)
            ->form([
                FileUpload::make('attachment')
                    ->label(__('الملف'))
                    ->required()
                    ->storeFiles(false)
                    ->maxSize(10 * 1024)
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->helperText(__('الحد الأقصى 10MB. الصيغ المدعومة: JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX.')),
            ])
            ->action(function (array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                $task = $this->getTask();
                $file = $data['attachment'] ?? null;

                if (! $file instanceof TemporaryUploadedFile) {
                    return;
                }

                $storedPath = $file->store("task-attachments/{$task->id}", 'local');

                $task->attachments()->create([
                    'user_id' => $actor->id,
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
                ]);

                $this->refreshTaskRecord();

                Notification::make()
                    ->title(__('تم رفع المرفق'))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('رفع مرفق للمهمة'))
            ->modalSubmitActionLabel(__('رفع'))
            ->modalWidth('lg');
    }

    protected function refreshTaskRecord(): void
    {
        /** @var Task|null $task */
        $task = $this->getTask()->fresh([
            'department.parent',
            'category',
            'reportedByUser',
            'createdByUser',
            'updatedByUser',
            'assignedToUser.department.parent',
            'latestAssignment.assignedByUser',
            'latestAssignment.assignedToUser.department.parent',
            'assignments.assignedByUser',
            'assignments.assignedToUser.department.parent',
            'comments.user.department.parent',
            'attachments.user',
            'assignmentTargets.target',
            'assignmentTargets.assignedByUser',
            'assignmentHistories.fromUser',
            'assignmentHistories.toUser',
            'assignmentHistories.performedByUser',
        ]);

        if ($task) {
            $this->record = $task;
        }
    }
}
