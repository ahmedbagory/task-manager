<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\User;
use App\Services\Tasks\TaskAttachmentService;
use App\Services\Tasks\TaskService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    public function getTitle(): string
    {
        return 'تعديل مهمة';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        $attachments = collect((array) ($data['attachment_uploads'] ?? []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->values();
        $removeAttachmentIds = collect((array) ($data['remove_attachment_ids'] ?? []))
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        unset($data['attachment_uploads'], $data['remove_attachment_ids']);

        $updatedTask = app(TaskService::class)->updateTask($record, $data, $user);

        if ($removeAttachmentIds !== []) {
            app(TaskAttachmentService::class)->removeTaskAttachments($updatedTask, $removeAttachmentIds);
        }

        if ($attachments->isNotEmpty()) {
            app(TaskAttachmentService::class)->storeUploadedAttachments($updatedTask, $attachments->all(), $user);
        }

        return $updatedTask->fresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->record
            ? 'تم تحديث المهمة '.$this->record->displayNumber()
            : 'تم تحديث المهمة بنجاح';
    }
}
