<?php

namespace App\Services\Inbound;

use App\Data\InboundMessageData;
use App\Enums\WhatsappMessageDirection;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Notifications\TaskWorkflowNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundMessageService
{
    public function __construct(
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
    ) {}

    public function handle(InboundMessageData $data): WhatsappMessage
    {
        $providerMessageId = $this->resolveProviderMessageId($data);

        try {
            return DB::transaction(function () use ($data, $providerMessageId): WhatsappMessage {
                $existing = WhatsappMessage::query()
                    ->where('whatsapp_message_id', $providerMessageId)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $contact = $this->findOrCreateContact(
                    phone: $data->fromPhone,
                    name: $data->senderName,
                    lastMessageAt: $data->receivedAt ?? now(),
                );

                $message = WhatsappMessage::query()->create([
                    'whatsapp_message_id' => $providerMessageId,
                    'contact_id' => $contact?->id,
                    'task_id' => null,
                    'direction' => WhatsappMessageDirection::INBOUND,
                    'from_phone' => $data->fromPhone,
                    'to_phone' => $data->toPhone,
                    'group_id' => $data->groupId,
                    'group_name' => $data->groupName,
                    'business_phone_number_id' => $data->businessPhoneNumberId,
                    'message_type' => $data->messageType ?? 'unknown',
                    'body' => $data->body,
                    'media_url' => $data->mediaUrl,
                    'status' => 'received',
                    'raw_payload' => $data->rawPayload,
                    'received_at' => $data->receivedAt ?? now(),
                    'sent_at' => null,
                ]);

                Log::info('Inbound message stored.', [
                    'provider' => $data->provider,
                    'message_id' => $providerMessageId,
                    'from_phone' => $data->fromPhone,
                    'group_id' => $data->groupId,
                    'message_type' => $data->messageType,
                ]);

                DB::afterCommit(function () use ($message): void {
                    $this->taskWorkflowNotificationService->notifyNewWhatsappMessageReceived($message);
                });

                return $message;
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateWhatsappMessageIdException($exception)) {
                $existing = WhatsappMessage::query()
                    ->where('whatsapp_message_id', $providerMessageId)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    private function findOrCreateContact(?string $phone, ?string $name, mixed $lastMessageAt): ?WhatsappContact
    {
        if (blank($phone)) {
            return null;
        }

        $contact = WhatsappContact::query()->firstOrNew(['phone' => $phone]);

        if (filled($name)) {
            $contact->name = $name;
        }

        if (filled($lastMessageAt)) {
            $contact->last_message_at = $lastMessageAt;
        }

        $contact->save();

        return $contact;
    }

    private function resolveProviderMessageId(InboundMessageData $data): string
    {
        $provided = trim((string) ($data->providerMessageId ?? ''));

        if ($provided !== '') {
            return $provided;
        }

        $receivedAt = $data->receivedAt?->utc()->timestamp;
        $rawPayloadHash = hash('sha256', json_encode($data->rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $fingerprint = implode('|', [
            strtolower($data->provider),
            strtolower((string) $data->fromPhone),
            strtolower((string) $data->toPhone),
            strtolower((string) $data->messageType),
            trim((string) $data->body),
            trim((string) $data->mediaUrl),
            (string) $receivedAt,
            $rawPayloadHash,
        ]);

        return 'auto_'.hash('sha256', $fingerprint);
    }

    private function isDuplicateWhatsappMessageIdException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'whatsapp_messages_whatsapp_message_id_unique')
            || str_contains($message, 'unique constraint failed: whatsapp_messages.whatsapp_message_id')
            || str_contains($message, 'duplicate entry');
    }
}
