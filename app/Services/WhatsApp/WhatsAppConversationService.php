<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsappMessageDirection;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Inbound\BridgeStatusService;
use App\Services\Settings\ApiSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppConversationService
{
    public function __construct(
        private readonly ApiSettingsService $apiSettingsService,
        private readonly BridgeApiClient $bridgeApiClient,
        private readonly WhatsAppMediaService $whatsAppMediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(array $data, ?User $actor = null, ?UploadedFile $attachment = null): WhatsAppSendResult
    {
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $groupId = $this->nullableString($data['group_id'] ?? null);
        $groupName = $this->nullableString($data['group_name'] ?? null);
        $body = $this->nullableString($data['body'] ?? null);
        $settings = $this->apiSettingsService->getWhatsAppSettings();
        $provider = strtolower((string) ($settings['provider'] ?? 'meta'));
        $outboundEnabled = filter_var($settings['outbound_enabled'] ?? false, FILTER_VALIDATE_BOOL) === true;
        $attachmentMeta = $attachment ? $this->whatsAppMediaService->storeOutgoingUpload($attachment) : null;
        $targetGroupId = $this->shouldSendToGroup($provider, $settings, $groupId) ? $groupId : null;
        $targetGroupName = $targetGroupId ? $groupName : null;
        $messageRecord = $this->createPendingMessageRecord(
            phone: $phone,
            groupId: $groupId,
            groupName: $groupName,
            body: $body,
            attachmentMeta: $attachmentMeta,
            actor: $actor,
            provider: $provider,
            targetGroupId: $targetGroupId,
            targetGroupName: $targetGroupName,
        );

        if (! $outboundEnabled) {
            return $this->markFailedAndRespond($messageRecord, __('WhatsApp outbound sending is disabled.'));
        }

        if ($provider !== BridgeStatusService::PROVIDER) {
            return $this->markFailedAndRespond($messageRecord, __('The current WhatsApp provider is not the local bridge.'));
        }

        return $this->dispatchBridgeSend(
            message: $messageRecord,
            body: $body,
            phone: $phone,
            groupId: $targetGroupId,
            groupName: $targetGroupName,
            attachmentMeta: $attachmentMeta,
        );
    }

    public function retry(WhatsappMessage $message, ?User $actor = null): WhatsAppSendResult
    {
        $message = $message->fresh();

        if (! $message || $message->direction !== WhatsappMessageDirection::OUTBOUND) {
            throw new \InvalidArgumentException('Only outbound WhatsApp messages can be retried.');
        }

        $settings = $this->apiSettingsService->getWhatsAppSettings();
        $provider = strtolower((string) ($settings['provider'] ?? 'meta'));

        if ($provider !== BridgeStatusService::PROVIDER) {
            return $this->markFailedAndRespond($message, __('The current WhatsApp provider is not the local bridge.'));
        }

        $message->forceFill([
            'status' => 'pending',
            'failed_reason' => null,
            'sent_by_user_id' => $actor?->id ?? $message->sent_by_user_id,
        ])->save();

        return $this->dispatchBridgeSend(
            message: $message->fresh(),
            body: $this->nullableString($message->body),
            phone: $this->normalizePhone($message->to_phone),
            groupId: $message->group_id,
            groupName: $message->group_name,
            attachmentMeta: $message->hasMedia() ? [
                'type' => $message->media_type,
                'mime_type' => $message->media_mime,
                'path' => $message->media_path,
                'url' => $message->media_url,
                'original_name' => $message->media_name,
                'size' => $message->media_size,
            ] : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $attachmentMeta
     */
    private function createPendingMessageRecord(
        string $phone,
        ?string $groupId,
        ?string $groupName,
        ?string $body,
        ?array $attachmentMeta,
        ?User $actor,
        string $provider,
        ?string $targetGroupId,
        ?string $targetGroupName,
    ): WhatsappMessage {
        $contact = $this->findOrCreateContact($phone);
        $now = now();

        $contact?->forceFill(['last_message_at' => $now])->save();

        return DB::transaction(function () use (
            $phone,
            $groupId,
            $groupName,
            $body,
            $attachmentMeta,
            $actor,
            $provider,
            $targetGroupId,
            $targetGroupName,
            $contact,
            $now,
        ): WhatsappMessage {
            return WhatsappMessage::query()->create([
                'whatsapp_message_id' => null,
                'external_message_id' => null,
                'contact_id' => $contact?->id,
                'task_id' => null,
                'sent_by_user_id' => $actor?->id,
                'direction' => WhatsappMessageDirection::OUTBOUND,
                'from_phone' => null,
                'to_phone' => $phone,
                'group_id' => $groupId,
                'group_name' => $groupName,
                'business_phone_number_id' => null,
                'message_type' => $attachmentMeta['type'] ?? 'text',
                'body' => $body,
                'media_url' => $attachmentMeta['url'] ?? null,
                'media_type' => $attachmentMeta['type'] ?? null,
                'media_mime' => $attachmentMeta['mime_type'] ?? null,
                'media_path' => $attachmentMeta['path'] ?? null,
                'media_name' => $attachmentMeta['original_name'] ?? null,
                'media_size' => $attachmentMeta['size'] ?? null,
                'media_rejected' => false,
                'media_reject_reason' => null,
                'status' => 'pending',
                'failed_reason' => null,
                'raw_payload' => [
                    'provider' => $provider,
                    'composer_target' => array_filter([
                        'phone' => $phone,
                        'group_id' => $groupId,
                        'group_name' => $groupName,
                        'resolved_group_id' => $targetGroupId,
                        'resolved_group_name' => $targetGroupName,
                    ]),
                ],
                'received_at' => null,
                'sent_at' => null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $attachmentMeta
     */
    private function dispatchBridgeSend(
        WhatsappMessage $message,
        ?string $body,
        string $phone,
        ?string $groupId,
        ?string $groupName,
        ?array $attachmentMeta,
    ): WhatsAppSendResult {
        $payload = array_filter([
            'to' => $phone,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'message' => $body,
            'attachment' => $attachmentMeta ? [
                'type' => $attachmentMeta['type'] ?? null,
                'mime_type' => $attachmentMeta['mime_type'] ?? null,
                'path' => $attachmentMeta['path'] ?? null,
                'url' => $attachmentMeta['url'] ?? null,
                'original_name' => $attachmentMeta['original_name'] ?? null,
                'size' => $attachmentMeta['size'] ?? null,
            ] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->bridgeApiClient->sendMessage($payload);

        if (($response['success'] ?? false) === true) {
            $updated = $this->markSent(
                message: $message,
                providerMessageId: $this->nullableString($response['provider_message_id'] ?? null),
                responseBody: $response,
            );

            return new WhatsAppSendResult(
                sent: true,
                status: 'sent',
                whatsappMessageId: $updated->external_message_id,
                httpStatusCode: 200,
                messageRecord: $updated,
                responseBody: $response,
            );
        }

        $error = $this->nullableString($response['error'] ?? null) ?: __('The bridge rejected the send request.');

        return $this->markFailedAndRespond($message, $error, $response);
    }

    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    private function markSent(WhatsappMessage $message, ?string $providerMessageId, ?array $responseBody = null): WhatsappMessage
    {
        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $rawPayload['bridge_send'] = $responseBody;

        $message->forceFill([
            'status' => 'sent',
            'failed_reason' => null,
            'whatsapp_message_id' => $providerMessageId ?: $message->whatsapp_message_id,
            'external_message_id' => $providerMessageId ?: $message->external_message_id,
            'raw_payload' => $rawPayload,
            'sent_at' => now(),
        ])->save();

        return $message->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    private function markFailedAndRespond(WhatsappMessage $message, string $reason, ?array $responseBody = null): WhatsAppSendResult
    {
        Log::warning('WhatsApp conversation send failed.', [
            'message_id' => $message->id,
            'reason' => $reason,
        ]);

        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $rawPayload['bridge_send'] = $responseBody;

        $message->forceFill([
            'status' => 'failed',
            'failed_reason' => $reason,
            'raw_payload' => $rawPayload,
        ])->save();

        $message = $message->fresh();

        return new WhatsAppSendResult(
            sent: false,
            status: 'failed',
            whatsappMessageId: null,
            httpStatusCode: $responseBody ? 422 : null,
            messageRecord: $message,
            responseBody: $responseBody,
        );
    }

    private function shouldSendToGroup(string $provider, array $settings, ?string $groupId): bool
    {
        if ($provider !== BridgeStatusService::PROVIDER) {
            return false;
        }

        $targetMode = strtolower((string) ($settings['bridge_outbound_target'] ?? 'direct_phone'));

        return $targetMode === 'same_group' && filled($groupId);
    }

    private function findOrCreateContact(string $phone): ?WhatsappContact
    {
        if ($phone === '') {
            return null;
        }

        $contact = WhatsappContact::query()->firstOrNew([
            'phone' => $phone,
        ]);

        if (! $contact->exists) {
            $contact->name = $contact->name ?: null;
        }

        $contact->save();

        return $contact;
    }

    private function normalizePhone(mixed $value): string
    {
        return preg_replace('/\D+/', '', ltrim(trim((string) $value), '+')) ?: '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
