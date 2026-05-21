<?php

namespace App\Services\WhatsApp;

use App\Enums\TaskSource;
use App\Enums\WhatsappMessageDirection;
use App\Jobs\SendWhatsAppTextMessageJob;
use App\Models\Task;
use App\Services\Inbound\BridgeStatusService;
use App\Services\Settings\ApiSettingsService;

class TaskWhatsAppNotificationService
{
    public function __construct(
        private readonly WhatsAppMessageTemplates $messageTemplates,
        private readonly ApiSettingsService $apiSettingsService,
    ) {}

    public function notifyTaskRegistered(Task $task): void
    {
        if (! $this->messageTemplates->isEnabled('task_registered')) {
            return;
        }

        $this->queueIfEligible($task, $this->messageTemplates->taskRegistered($task));
    }

    public function notifyTaskAssigned(Task $task): void
    {
        if (! $this->messageTemplates->isEnabled('task_assigned')) {
            return;
        }

        $task->loadMissing('assignedToUser');

        $extra = [];
        if ($task->assignedToUser) {
            $extra['assignee_name'] = $task->assignedToUser->name;
        }

        $this->queueIfEligible($task, $this->messageTemplates->taskAssigned($task, $extra));
    }

    public function notifyTaskCompleted(Task $task): void
    {
        if (! $this->messageTemplates->isEnabled('task_completed')) {
            return;
        }

        $this->queueIfEligible($task, $this->messageTemplates->taskCompleted($task));
    }

    private function queueIfEligible(Task $task, string $message): void
    {
        if (! $this->shouldSendForTask($task)) {
            return;
        }

        $target = $this->resolveTarget($task);

        if (blank($target['phone']) && blank($target['group_id'])) {
            return;
        }

        SendWhatsAppTextMessageJob::dispatch(
            phone: (string) ($target['phone'] ?? ''),
            message: $message,
            taskId: $task->id,
            groupId: $target['group_id'] ?? null,
            groupName: $target['group_name'] ?? null,
        );
    }

    private function shouldSendForTask(Task $task): bool
    {
        $source = $task->source instanceof TaskSource
            ? $task->source
            : TaskSource::tryFrom((string) $task->source);

        return $source === TaskSource::WHATSAPP;
    }

    /**
     * @return array{phone:?string, group_id:?string, group_name:?string}
     */
    private function resolveTarget(Task $task): array
    {
        $phone = $this->nullableString($task->reported_by_phone);
        $settings = $this->apiSettingsService->getWhatsAppSettings();
        $provider = strtolower((string) ($settings['provider'] ?? 'meta'));
        $targetMode = strtolower((string) ($settings['bridge_outbound_target'] ?? 'direct_phone'));

        if ($provider !== BridgeStatusService::PROVIDER || $targetMode !== 'same_group') {
            return [
                'phone' => $phone,
                'group_id' => null,
                'group_name' => null,
            ];
        }

        $groupMessage = $task->whatsappMessages()
            ->where('direction', WhatsappMessageDirection::INBOUND->value)
            ->whereNotNull('group_id')
            ->orderBy('id')
            ->first(['group_id', 'group_name']);

        if (! $groupMessage || blank($groupMessage->group_id)) {
            return [
                'phone' => $phone,
                'group_id' => null,
                'group_name' => null,
            ];
        }

        return [
            'phone' => $phone,
            'group_id' => $this->nullableString($groupMessage->group_id),
            'group_name' => $this->nullableString($groupMessage->group_name),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
