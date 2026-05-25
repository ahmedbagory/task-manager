<?php

namespace App\Services\Inbound;

use App\Data\InboundMessageData;
use App\Enums\WhatsappMessageDirection;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Services\WhatsApp\WhatsAppMediaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundMessageService
{
    public function __construct(
        private readonly TaskWorkflowNotificationService $taskWorkflowNotificationService,
        private readonly WhatsAppMediaService $whatsAppMediaService,
    ) {}

    public function handle(InboundMessageData $data): WhatsappMessage
    {
        $providerMessageId = $this->resolveProviderMessageId($data);

        try {
            $wasCreated = false;

            $message = DB::transaction(function () use ($data, $providerMessageId, &$wasCreated): WhatsappMessage {
                $existing = WhatsappMessage::query()
                    ->where('whatsapp_message_id', $providerMessageId)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $safePhone = $this->sanitizePhone($data->fromPhone);

                $contact = $this->findOrCreateContact(
                    phone: $safePhone,
                    name: $data->senderName,
                    lastMessageAt: $data->receivedAt ?? now(),
                );

                $message = WhatsappMessage::query()->create([
                    'whatsapp_message_id' => $providerMessageId,
                    'contact_id' => $contact?->id,
                    'task_id' => null,
                    'direction' => WhatsappMessageDirection::INBOUND,
                    'from_phone' => $safePhone,
                    'to_phone' => $data->toPhone,
                    'group_id' => $data->groupId,
                    'group_name' => $data->groupName,
                    'business_phone_number_id' => $data->businessPhoneNumberId,
                    'message_type' => $data->messageType ?? 'unknown',
                    'body' => $data->body,
                    'media_url' => $this->resolveMediaUrl($data),
                    'media_type' => $data->mediaType,
                    'media_mime' => $data->mediaMime,
                    'media_path' => $data->mediaPath,
                    'media_name' => $data->mediaName,
                    'media_size' => $data->mediaSize,
                    'media_rejected' => $data->mediaRejected,
                    'media_reject_reason' => $data->mediaRejectReason,
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
                    'media_type' => $data->mediaType,
                    'media_rejected' => $data->mediaRejected,
                ]);

                $wasCreated = true;

                return $message;
            });

            if ($wasCreated) {
                $notificationMessage = WhatsappMessage::query()
                    ->with('contact')
                    ->find($message->id);

                if ($notificationMessage) {
                    $this->taskWorkflowNotificationService->notifyNewWhatsappMessageReceived($notificationMessage);
                }
            }

            return $message;
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

        if (! $this->isValidContactPhone($phone)) {
            Log::info('Skipped contact creation for invalid phone.', ['phone' => $phone]);

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

    private function sanitizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $hasLeadingPlus = str_starts_with(trim((string) $phone), '+');
        $clean = preg_replace('/[@:].*$/', '', $phone);
        $clean = preg_replace('/\D/', '', $clean);
        $clean = $hasLeadingPlus ? '+'.$clean : $clean;

        return $this->isValidContactPhone($clean) ? $clean : null;
    }

    private function isValidContactPhone(?string $phone): bool
    {
        if (blank($phone)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return false;
        }

        if (str_starts_with($digits, '120363')) {
            return false;
        }

        $lower = strtolower($phone);

        if (str_ends_with($lower, '@g.us') || str_ends_with($lower, '@broadcast') || str_ends_with($lower, '@newsletter')) {
            return false;
        }

        return true;
    }

    private function isDuplicateWhatsappMessageIdException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'whatsapp_messages_whatsapp_message_id_unique')
            || str_contains($message, 'unique constraint failed: whatsapp_messages.whatsapp_message_id')
            || str_contains($message, 'duplicate entry');
    }

    private function resolveMediaUrl(InboundMessageData $data): ?string
    {
        if (filled($data->mediaPath) && $this->whatsAppMediaService->mediaFileExists($data->mediaPath)) {
            return $this->whatsAppMediaService->publicUrlForStoredPath($data->mediaPath);
        }

        if (filled($data->mediaUrl)) {
            return $data->mediaUrl;
        }

        return $this->whatsAppMediaService->publicUrlForStoredPath($data->mediaPath);
    }
}
