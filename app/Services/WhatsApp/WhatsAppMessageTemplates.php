<?php

namespace App\Services\WhatsApp;

use App\Models\Task;
use App\Services\Settings\ApiSettingsService;

class WhatsAppMessageTemplates
{
    public function __construct(
        private readonly ApiSettingsService $apiSettingsService,
    ) {}

    public function taskRegistered(Task $task): string
    {
        return $this->render('task_registered', $task);
    }

    /**
     * @param  array<string, string>  $extra
     */
    public function taskAssigned(Task $task, array $extra = []): string
    {
        return $this->render('task_assigned', $task, $extra);
    }

    public function taskCompleted(Task $task): string
    {
        return $this->render('task_completed', $task);
    }

    public function isEnabled(string $key): bool
    {
        $templates = $this->apiSettingsService->getMessageTemplates();

        return filter_var($templates["enabled_{$key}"] ?? true, FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function render(string $key, Task $task, array $extra = []): string
    {
        $templates = $this->apiSettingsService->getMessageTemplates();
        $defaults = ApiSettingsService::defaultMessageTemplates();

        $template = trim($templates[$key] ?? $defaults[$key] ?? '');

        if ($template === '') {
            $template = $defaults[$key] ?? '';
        }

        $assigneeName = $extra['assignee_name']
            ?? $task->assignedToUser?->name
            ?? '';

        return str_replace(
            ['{task_number}', '{task_title}', '{reporter_phone}', '{assignee_name}'],
            [$task->task_number ?? '', $task->title ?? '', $task->reported_by_phone ?? '', $assigneeName],
            $template,
        );
    }
}
