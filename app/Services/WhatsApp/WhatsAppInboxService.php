<?php

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsAppMessageAlreadyConvertedException;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Services\Tasks\TaskAttachmentService;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WhatsAppInboxService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskAttachmentService $taskAttachmentService,
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function convertMessageToTask(WhatsappMessage $message, array $data, User $actor): Task
    {
        Gate::forUser($actor)->authorize('convertToTask', $message);

        $task = DB::transaction(function () use ($message, $data, $actor): Task {
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

            $this->taskAttachmentService->copyFromWhatsappMessage($task, $lockedMessage, $actor);

            return $task->fresh();
        });

        $freshMessage = WhatsappMessage::query()->with('contact')->find($message->id);
        $freshTask = Task::query()->find($task->id);

        if ($freshMessage && $freshTask) {
            $this->taskWorkflowNotificationService->notifyWhatsappMessageConvertedToTask(
                message: $freshMessage,
                task: $freshTask,
            );
        }

        return $task;
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
        $description = $this->messageDescription($message);

        $title = trim((string) Arr::get($data, 'title', ''));

        if ($title === '') {
            $title = str($messageBody !== '' ? $messageBody : 'مرفق من واتساب')
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
            'reported_by_user_id' => Arr::get(
                $data,
                'reported_by_user_id',
                $routingContact?->user_id ?: $this->resolveMatchedUserId($resolvedReporterPhone),
            ),
            'whatsapp_contact_id' => $routingContact?->id ?? $message->contact_id,
            'priority' => Arr::get($data, 'priority'),
            'location' => filled($selectedLocation) ? $selectedLocation : $contactLocation,
            'due_at' => Arr::get($data, 'due_at'),
            'assignee_ids' => array_map('intval', (array) Arr::get($data, 'assignee_ids', [])),
            'reported_by_phone' => $resolvedReporterPhone,
        ];
    }

    private function messageDescription(WhatsappMessage $message): ?string
    {
        $body = trim((string) ($message->body ?? ''));

        if ($body !== '') {
            return $body;
        }

        return $message->hasMedia() ? 'مرفق من واتساب' : null;
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

    private function resolveMatchedUserId(?string $phone): ?int
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone);

        if (! filled($normalized)) {
            return null;
        }

        return User::query()
            ->where(function ($query) use ($normalized): void {
                $query
                    ->where('phone', $normalized)
                    ->orWhere('phone', '+'.$normalized)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$normalized]);
            })
            ->value('id');
    }
}
