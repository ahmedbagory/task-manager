<?php

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
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
            title: __('تم تحويل رسالة واتساب إلى مهمة :number', ['number' => $task->displayNumber()]),
            body: $senderDisplay.' → '.$task->title,
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'success',
            icon: 'heroicon-o-check-badge',
        );
    }

    public function notifyTaskAssignedToEmployee(Task $task, TaskAssignment $assignment, string $context = 'new_assignment'): void
    {
        $recipient = $assignment->assignedToUser ?: User::query()->find($assignment->assigned_to_user_id);

        if (! $recipient) {
            return;
        }

        $assigner = $assignment->assignedByUser?->name ?? __('النظام');
        [$title, $body] = $this->assignmentCopy($task, $assigner, $context);

        $this->sendDatabaseNotification(
            recipients: collect([$recipient]),
            title: $title,
            body: $body,
            url: route('my-tasks.show', ['task' => $task]),
            status: 'warning',
            icon: 'heroicon-o-user-plus',
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    public function notifyTaskAssignedToUsers(
        Task $task,
        Collection $recipients,
        ?User $actor = null,
        string $context = 'new_assignment'
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $assigner = $actor?->name ?? __('النظام');
        [$title, $body] = $this->assignmentCopy($task, $assigner, $context);

        $this->sendDatabaseNotification(
            recipients: $recipients->unique('id')->values(),
            title: $title,
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
            title: __('تم رفض المهمة :number', ['number' => $task->displayNumber()]),
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
            title: __('تم إكمال المهمة :number', ['number' => $task->displayNumber()]),
            body: $body,
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'success',
            icon: 'heroicon-o-check-circle',
        );
    }

    public function notifyReporterConfirmationRequested(Task $task, User $reporter): void
    {
        $this->sendDatabaseNotification(
            recipients: collect([$reporter]),
            title: 'بانتظار تأكيد حل المشكلة',
            body: 'تم إرسال المهمة '.$task->displayNumber().': '.$task->title.' للتأكيد. هل تم حل المشكلة؟',
            url: route('my-tasks.show', ['task' => $task]),
            status: 'warning',
            icon: 'heroicon-o-chat-bubble-left-right',
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    public function notifyReporterRejectedResolution(Task $task, Collection $recipients): void
    {
        $this->sendDatabaseNotification(
            recipients: $recipients->unique('id')->values(),
            title: 'المبلّغ أكد أن المشكلة لم تُحل',
            body: 'تم رفض إغلاق المهمة '.$task->displayNumber().': '.$task->title.'. راجع التعليق وأكمل المتابعة.',
            url: route('my-tasks.show', ['task' => $task]),
            status: 'danger',
            icon: 'heroicon-o-exclamation-triangle',
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    public function notifyReporterConfirmedResolution(Task $task, Collection $recipients): void
    {
        $this->sendDatabaseNotification(
            recipients: $recipients->unique('id')->values(),
            title: 'تم تأكيد حل المشكلة',
            body: 'أكد المبلّغ حل المشكلة وتم إغلاق المهمة '.$task->displayNumber().': '.$task->title,
            url: route('my-tasks.show', ['task' => $task]),
            status: 'success',
            icon: 'heroicon-o-check-badge',
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    public function notifyTaskReopenedToAssignees(Task $task, Collection $recipients, ?User $actor = null): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $actorName = $actor?->name ?? __('النظام');

        $this->sendDatabaseNotification(
            recipients: $recipients->unique('id')->values(),
            title: 'تمت إعادة فتح المهمة',
            body: $actorName.' أعاد فتح المهمة '.$task->displayNumber().': '.$task->title,
            url: route('my-tasks.show', ['task' => $task]),
            status: 'warning',
            icon: 'heroicon-o-arrow-path',
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

        $payload = $notification->getDatabaseMessage();

        foreach ($recipients->unique('id') as $recipient) {
            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => FilamentDatabaseNotification::class,
                'data' => $payload,
                'read_at' => null,
            ]);
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

    /**
     * @return array{0: string, 1: string}
     */
    private function assignmentCopy(Task $task, string $actorName, string $context): array
    {
        $taskLine = $task->displayNumber().': '.$task->title;

        return match ($context) {
            'added_assignee' => [
                'تمت إضافتك إلى مهمة',
                'تمت إضافتك ضمن فريق العمل على المهمة '.$taskLine,
            ],
            'reassigned' => [
                'تمت إعادة تعيين مهمة إليك',
                'تم نقل/إعادة تعيين المهمة '.$taskLine.' إليك بواسطة '.$actorName,
            ],
            default => [
                'تم إسناد مهمة جديدة إليك',
                'تم إسناد المهمة '.$taskLine.' إليك بواسطة '.$actorName,
            ],
        };
    }
}
