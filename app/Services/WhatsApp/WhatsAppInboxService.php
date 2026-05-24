<?php

namespace App\Services\WhatsApp;

use App\Enums\TaskStatus;
use App\Exceptions\WhatsAppMessageAlreadyConvertedException;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use App\Services\Tasks\TaskAssignmentService;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WhatsAppInboxService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskAssignmentService $taskAssignmentService,
        private readonly TaskAssignmentTargetResolver $taskAssignmentTargetResolver,
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function convertMessageToTask(WhatsappMessage $message, array $data, User $actor): Task
    {
        Gate::forUser($actor)->authorize('convertToTask', $message);

        return DB::transaction(function () use ($message, $data, $actor): Task {
            $lockedMessage = WhatsappMessage::query()
                ->lockForUpdate()
                ->with('task')
                ->findOrFail($message->id);

            if ($lockedMessage->task) {
                throw new WhatsAppMessageAlreadyConvertedException($lockedMessage->task);
            }

            $task = $this->taskService->createTaskFromWhatsAppMessage(
                message: $lockedMessage,
                data: $this->buildTaskPayload($lockedMessage, $data),
                actor: $actor,
            );

            $directAssigneeId = Arr::get($data, 'assigned_to_user_id');
            $hasTargets = ! empty($data['assign_to_all'])
                || ! empty($data['assignment_target_departments'])
                || ! empty($data['assignment_target_units'])
                || ! empty($data['assignment_target_users']);

            if (filled($directAssigneeId)) {
                $this->taskAssignmentService->assignTask(
                    task: $task,
                    assignedToUserId: (int) $directAssigneeId,
                    assignedBy: $actor,
                );
            } elseif ($hasTargets) {
                $this->taskAssignmentTargetResolver->syncTargets($task, [
                    'all' => ! empty($data['assign_to_all']),
                    'departments' => array_map('intval', (array) ($data['assignment_target_departments'] ?? [])),
                    'units' => array_map('intval', (array) ($data['assignment_target_units'] ?? [])),
                    'users' => array_map('intval', (array) ($data['assignment_target_users'] ?? [])),
                ], $actor);
            }

            DB::afterCommit(function () use ($lockedMessage, $task): void {
                $freshMessage = WhatsappMessage::query()->find($lockedMessage->id);
                $freshTask = Task::query()->find($task->id);

                if ($freshMessage && $freshTask) {
                    $this->taskWorkflowNotificationService->notifyWhatsappMessageConvertedToTask(
                        message: $freshMessage,
                        task: $freshTask,
                    );
                }
            });

            return $task->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildTaskPayload(WhatsappMessage $message, array $data): array
    {
        $message->loadMissing('contact');
        [$routingContact, $resolvedReporterPhone] = $this->resolveRoutingContext($message);

        $messageBody = trim((string) ($message->body ?? ''));
        $additionalDescription = trim((string) Arr::get($data, 'description', ''));

        $description = $messageBody;

        if (($additionalDescription !== '') && ($additionalDescription !== $messageBody)) {
            $description = trim($messageBody === ''
                ? $additionalDescription
                : ($messageBody.PHP_EOL.PHP_EOL.$additionalDescription));
        }

        $title = trim((string) Arr::get($data, 'title', ''));

        if ($title === '') {
            $title = str($messageBody !== '' ? $messageBody : 'WhatsApp issue report')
                ->limit(100)
                ->toString();
        }

        $contactDepartmentId = $routingContact?->department_id;
        $contactLocation = $routingContact?->default_location;
        $selectedDepartmentId = Arr::get($data, 'department_id');
        $selectedLocation = Arr::get($data, 'location');

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'department_id' => filled($selectedDepartmentId) ? $selectedDepartmentId : $contactDepartmentId,
            'category_id' => Arr::get($data, 'category_id'),
            'priority' => Arr::get($data, 'priority'),
            'location' => filled($selectedLocation) ? $selectedLocation : $contactLocation,
            'due_at' => Arr::get($data, 'due_at'),
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'reported_by_phone' => $resolvedReporterPhone,
        ];
    }

    /**
     * @return array{0: WhatsappContact|null, 1: string|null}
     */
    private function resolveRoutingContext(WhatsappMessage $message): array
    {
        $contact = $message->contact;
        $reporterPhone = $message->from_phone ?: $message->contact?->phone;

        $participantPhone = $this->normalizePhoneFromJid($this->extractParticipantJid($message));

        if (filled($participantPhone)) {
            $reporterPhone = $participantPhone;

            if ($contact === null || (blank($contact->department_id) && blank($contact->default_location))) {
                $contact = WhatsappContact::query()
                    ->whereIn('phone', [$participantPhone, '+'.$participantPhone])
                    ->first() ?? $contact;
            }
        }

        return [$contact, $reporterPhone];
    }

    private function extractParticipantJid(WhatsappMessage $message): ?string
    {
        return Arr::get($message->raw_payload, 'payload.raw_payload.key.participantPn')
            ?? Arr::get($message->raw_payload, 'payload.raw_payload.key.participantPN')
            ?? Arr::get($message->raw_payload, 'payload.raw_payload.key.participant_pn')
            ?? Arr::get($message->raw_payload, 'bridge_payload.key.participantPn')
            ?? Arr::get($message->raw_payload, 'bridge_payload.key.participantPN')
            ?? Arr::get($message->raw_payload, 'bridge_payload.key.participant_pn');
    }

    private function normalizePhoneFromJid(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $beforeAt = explode('@', $value)[0] ?? $value;
        $normalized = ltrim(trim($beforeAt), '+');

        return $normalized === '' ? null : $normalized;
    }
}
