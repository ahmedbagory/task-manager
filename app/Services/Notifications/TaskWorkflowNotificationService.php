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

        $sender = $message->from_phone ?: 'Unknown sender';
        $snippet = str((string) ($message->body ?: 'No message body'))->limit(120)->toString();

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: 'New WhatsApp message received',
            body: "From: {$sender}\n{$snippet}",
            url: route('filament.admin.resources.whatsapp-messages.view', ['record' => $message]),
            status: 'info',
        );
    }

    public function notifyWhatsappMessageConvertedToTask(WhatsappMessage $message, Task $task): void
    {
        $recipients = $this->dispatcherAndAdminRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $sender = $message->from_phone ?: 'Unknown sender';

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: "WhatsApp message converted to task {$task->task_number}",
            body: "Sender: {$sender}\nTask: {$task->title}",
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'success',
        );
    }

    public function notifyTaskAssignedToEmployee(Task $task, TaskAssignment $assignment): void
    {
        $recipient = $assignment->assignedToUser ?: User::query()->find($assignment->assigned_to_user_id);

        if (! $recipient) {
            return;
        }

        $body = "Task: {$task->title}";

        if (filled($assignment->note)) {
            $body .= "\nNote: {$assignment->note}";
        }

        $this->sendDatabaseNotification(
            recipients: collect([$recipient]),
            title: "Task {$task->task_number} assigned to you",
            body: $body,
            url: route('my-tasks.show', ['task' => $task]),
            status: 'warning',
        );
    }

    public function notifyTaskRejectedToDispatchers(Task $task, TaskAssignment $assignment, User $actor): void
    {
        $recipients = $this->dispatcherAndAdminRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $reason = filled($assignment->note) ? $assignment->note : 'No reason provided.';

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: "Task {$task->task_number} was rejected",
            body: "By: {$actor->name}\nReason: {$reason}",
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'danger',
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

        $body = "Task: {$task->title}";

        if (filled($completedBy)) {
            $body .= "\nCompleted by: {$completedBy}";
        }

        $this->sendDatabaseNotification(
            recipients: $recipients,
            title: "Task {$task->task_number} completed",
            body: $body,
            url: route('filament.admin.resources.tasks.view', ['record' => $task]),
            status: 'success',
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
        string $status = 'info'
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->status($status);

        if ($url) {
            $notification->actions([
                Action::make('view')
                    ->label('View')
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
