<?php

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class TaskWorkflowNotificationService
{
    public function notifyNewWhatsappMessageReceived(WhatsappMessage $message): void
    {
        $recipients = $this->dispatcherAndAdminRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $contactName = $message->contact?->name;
        $phone = $message->from_phone ?: __('رقم غير معروف');
        $senderLine = $contactName ?: $phone;

        $snippetSource = $message->body;

        if (blank($snippetSource) && $message->hasMedia()) {
            $snippetSource = match ($message->media_type) {
                'image' => '📷 '.__('صورة'),
                'document' => '📄 '.__('مستند'),
                'audio' => '🎵 '.__('صوت'),
                'video' => '🎬 '.__('فيديو'),
                'sticker' => '🏷️ '.__('ملصق'),
                default => '📎 '.__('وسائط'),
            };
        }

        $snippet = str((string) ($snippetSource ?: __('بدون نص')))->limit(80)->toString();

        $title = $contactName
            ? __('رسالة واتساب جديدة من :name', ['name' => $contactName])
            : __('رسالة واتساب جديدة');

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: $title,
            body: $snippet,
            url: route('filament.admin.resources.whatsapp-messages.view', ['record' => $message]),
            status: 'info',
            icon: 'heroicon-o-chat-bubble-left-ellipsis',
        );
    }

    public function notifyWhatsappMessageConvertedToTask(WhatsappMessage $message, Task $task): void
    {
        $recipients = $this->dispatcherAndAdminRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $contactName = $message->contact?->name;
        $senderDisplay = $contactName ?: ($message->from_phone ?: __('رقم غير معروف'));

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: __('تم تحويل رسالة واتساب إلى مهمة :number', ['number' => $task->task_number]),
            body: $senderDisplay.' → '.$task->title,
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'success',
            icon: 'heroicon-o-check-badge',
        );
    }

    public function notifyTaskAssignedToEmployee(Task $task, TaskAssignment $assignment): void
    {
        $recipient = $assignment->assignedToUser ?: User::query()->find($assignment->assigned_to_user_id);

        if (! $recipient) {
            return;
        }

        $assigner = $assignment->assignedByUser?->name ?? __('النظام');
        $parts = ['<p>'.$task->title.'</p>'];

        if (filled($assignment->note)) {
            $parts[] = '<p>'.__('ملاحظة').': '.str($assignment->note)->limit(60)->toString().'</p>';
        }

        $parts[] = '<p>'.__('بواسطة').': '.$assigner.'</p>';
        $body = implode('', $parts);

        $this->sendDatabaseNotification(
            recipients: collect([$recipient]),
            title: __('تم إسناد المهمة :number إليك', ['number' => $task->task_number]),
            body: $body,
            url: route('my-tasks.show', ['task' => $task]),
            status: 'warning',
            icon: 'heroicon-o-user-plus',
        );
    }

    public function notifyTaskRejectedToDispatchers(Task $task, TaskAssignment $assignment, User $actor): void
    {
        $recipients = $this->dispatcherAndAdminRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $reason = filled($assignment->note)
            ? str($assignment->note)->limit(60)->toString()
            : __('لم يتم تقديم سبب');

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: __('تم رفض المهمة :number', ['number' => $task->task_number]),
            body: $actor->name.' — '.$reason,
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'danger',
            icon: 'heroicon-o-x-circle',
        );
    }

    public function notifyTaskCompletedToDispatchers(Task $task, ?TaskAssignment $assignment = null): void
    {
        $recipients = $this->dispatcherAndAdminRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $completedBy = $assignment?->assignedToUser?->name;

        if (! $completedBy && $task->assigned_to_user_id) {
            $completedBy = User::query()->whereKey($task->assigned_to_user_id)->value('name');
        }

        $body = '<p>'.$task->title.'</p>';

        if (filled($completedBy)) {
            $body .= '<p>'.__('أكملها').': '.$completedBy.'</p>';
        }

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: __('تم إكمال المهمة :number', ['number' => $task->task_number]),
            body: $body,
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'success',
            icon: 'heroicon-o-check-circle',
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function sendDatabaseNotification(
        Collection $recipients,
        string $title,
        string $body,
        ?string $url = null,
        string $status = 'info',
        ?string $icon = null
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->status($status);

        if ($icon) {
            $notification->icon($icon);
        }

        if ($url) {
            $notification->actions([
                Action::make('view')
                    ->label(__('عرض'))
                    ->button()
                    ->markAsRead()
                    ->url($url),
            ]);
        }

        foreach ($recipients as $recipient) {
            $recipient->notifyNow($notification->toDatabase());
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function dispatcherAndAdminRecipients(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                Rbac::SUPER_ADMIN,
                Rbac::ADMIN,
                Rbac::DISPATCHER,
            ]))
            ->get()
            ->unique('id')
            ->values();
    }
}
