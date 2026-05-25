<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Tasks\TaskAttachmentService;
use App\Services\Tasks\TaskService;
use App\Services\WhatsApp\WhatsAppInboxService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    public function mount(): void
    {
        parent::mount();

        $messageId = request()->integer('whatsapp_message');

        if ($messageId <= 0) {
            return;
        }

        $message = WhatsappMessage::query()
            ->with(['contact', 'task'])
            ->findOrFail($messageId);

        abort_unless(auth()->user()?->can('convertToTask', $message), 403);

        if ($message->task) {
            Notification::make()
                ->title(__('This WhatsApp message is already linked to task :task.', ['task' => $message->task->displayNumber()]))
                ->warning()
                ->send();

            $this->redirect(TaskResource::getUrl('view', ['record' => $message->task]), navigate: true);

            return;
        }

        $this->form->fill($this->buildPrefillData($message));
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $attachments = collect((array) ($data['attachment_uploads'] ?? []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->values();
        $whatsappMessageId = (int) ($data['whatsapp_message_id'] ?? 0);

        unset($data['attachment_uploads'], $data['remove_attachment_ids'], $data['whatsapp_message_id']);

        if ($whatsappMessageId > 0) {
            $message = WhatsappMessage::query()
                ->with(['contact', 'task'])
                ->findOrFail($whatsappMessageId);

            $task = app(WhatsAppInboxService::class)->convertMessageToTask($message, $data, $user);
        } else {
            $task = app(TaskService::class)->createManualTask($data, $user);
        }

        if ($attachments->isNotEmpty()) {
            app(TaskAttachmentService::class)->storeUploadedAttachments($task, $attachments->all(), $user);
        }

        return $task->fresh();
    }

    protected function getRedirectUrl(): string
    {
        return TaskResource::getUrl('view', ['record' => $this->record]);
    }

    public function getTitle(): string
    {
        return 'إنشاء مهمة';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->record
            ? 'تم إنشاء المهمة '.$this->record->displayNumber()
            : 'تم إنشاء المهمة بنجاح';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPrefillData(WhatsappMessage $message): array
    {
        $body = trim((string) ($message->body ?? ''));
        $phone = $message->from_phone ?: $message->contact?->phone ?: $message->to_phone;
        $description = $body !== ''
            ? $body
            : ($message->hasMedia() ? 'مرفق من واتساب' : null);

        return [
            'whatsapp_message_id' => $message->id,
            'title' => $body !== ''
                ? __('متابعة واتساب: :text', ['text' => Str::limit($body, 60)])
                : __('مرفق من واتساب'),
            'description' => $description,
            'priority' => TaskPriority::MEDIUM->value,
            'source' => TaskSource::WHATSAPP->value,
            'department_id' => $message->contact?->department_id,
            'category_id' => $this->resolveDefaultCategoryId(),
            'location' => $message->contact?->default_location,
            'reported_by_user_id' => $message->contact?->user_id ?: $this->resolveMatchedRequesterUserId($phone),
            'reported_by_phone' => $phone,
        ];
    }

    private function resolveDefaultCategoryId(): ?int
    {
        return TaskCategory::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query
                    ->where('name', 'like', '%واتساب%')
                    ->orWhere('name', 'like', '%WhatsApp%')
                    ->orWhere('name', 'like', '%متابعة عملاء%')
                    ->orWhere('name', 'like', '%Customer%');
            })
            ->orderBy('name')
            ->value('id');
    }

    private function resolveMatchedRequesterUserId(?string $phone): ?int
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone);

        if (! filled($normalized)) {
            return null;
        }

        return User::query()
            ->where(function (Builder $query) use ($normalized): void {
                $query
                    ->where('phone', $normalized)
                    ->orWhere('phone', '+'.$normalized)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$normalized]);
            })
            ->value('id');
    }
}
