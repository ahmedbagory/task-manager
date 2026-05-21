<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsappMessageDirection;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Inbound\BridgeStatusService;
use App\Services\Settings\ApiSettingsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppClient
{
    public function __construct(
        private readonly ApiSettingsService $apiSettingsService,
    ) {}

    public function sendTextMessage(
        string $phone,
        string $message,
        ?string $groupId = null,
        ?string $groupName = null,
    ): WhatsAppSendResult {
        $phone = trim($phone);
        $message = trim($message);
        $groupId = $this->normalizeNullableString($groupId);
        $groupName = $this->normalizeNullableString($groupName);
        $settings = $this->apiSettingsService->getWhatsAppSettings();
        $provider = strtolower((string) ($settings['provider'] ?? 'meta'));

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        if (! $this->isOutboundEnabled()) {
            $record = $this->storeOutboundMessage(
                phone: $phone,
                message: $message,
                status: 'pending_disabled',
                rawPayload: [
                    'provider' => $provider,
                    'outbound_enabled' => false,
                    'request' => $payload,
                    'target' => $this->buildTargetPayload($phone, $groupId, $groupName),
                ],
                groupId: $groupId,
                groupName: $groupName,
            );

            return new WhatsAppSendResult(
                sent: false,
                status: 'pending_disabled',
                whatsappMessageId: null,
                httpStatusCode: null,
                messageRecord: $record,
                responseBody: null,
            );
        }

        if ($provider === BridgeStatusService::PROVIDER) {
            return $this->queueBridgeOutboundMessage($phone, $message, $groupId, $groupName, $payload);
        }

        $accessToken = (string) ($settings['access_token'] ?? '');
        $phoneNumberId = (string) ($settings['phone_number_id'] ?? '');

        if ($accessToken === '' || $phoneNumberId === '') {
            $record = $this->storeOutboundMessage(
                phone: $phone,
                message: $message,
                status: 'failed_configuration',
                rawPayload: [
                    'provider' => $provider,
                    'outbound_enabled' => true,
                    'request' => $payload,
                    'target' => $this->buildTargetPayload($phone, $groupId, $groupName),
                    'error' => 'Missing WhatsApp outbound configuration.',
                ],
                groupId: $groupId,
                groupName: $groupName,
            );

            return new WhatsAppSendResult(
                sent: false,
                status: 'failed_configuration',
                whatsappMessageId: null,
                httpStatusCode: null,
                messageRecord: $record,
                responseBody: null,
            );
        }

        $url = $this->buildMessageApiUrl($phoneNumberId, $settings);

        try {
            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->post($url, $payload);
        } catch (Throwable $throwable) {
            $record = $this->storeOutboundMessage(
                phone: $phone,
                message: $message,
                status: 'failed_exception',
                rawPayload: [
                    'provider' => $provider,
                    'outbound_enabled' => true,
                    'request' => $payload,
                    'target' => $this->buildTargetPayload($phone, $groupId, $groupName),
                    'error' => $throwable->getMessage(),
                ],
                groupId: $groupId,
                groupName: $groupName,
            );

            return new WhatsAppSendResult(
                sent: false,
                status: 'failed_exception',
                whatsappMessageId: null,
                httpStatusCode: null,
                messageRecord: $record,
                responseBody: null,
            );
        }

        $responseBody = $response->json();
        $responseBody = is_array($responseBody) ? $responseBody : null;
        $metaMessageId = Arr::get($responseBody, 'messages.0.id');

        $status = $response->successful() ? 'sent' : 'failed';
        $sentAt = $response->successful() ? now() : null;

        $record = $this->storeOutboundMessage(
            phone: $phone,
            message: $message,
            status: $status,
            whatsappMessageId: is_string($metaMessageId) ? $metaMessageId : null,
            rawPayload: [
                'provider' => $provider,
                'outbound_enabled' => true,
                'request' => $payload,
                'target' => $this->buildTargetPayload($phone, $groupId, $groupName),
                'response' => $responseBody,
                'http_status' => $response->status(),
            ],
            businessPhoneNumberId: $phoneNumberId,
            sentAt: $sentAt,
            groupId: $groupId,
            groupName: $groupName,
        );

        return new WhatsAppSendResult(
            sent: $response->successful(),
            status: $status,
            whatsappMessageId: is_string($metaMessageId) ? $metaMessageId : null,
            httpStatusCode: $response->status(),
            messageRecord: $record,
            responseBody: $responseBody,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function buildMessageApiUrl(string $phoneNumberId, array $settings): string
    {
        $baseUrl = rtrim((string) ($settings['api_base_url'] ?? 'https://graph.facebook.com'), '/');
        $graphVersion = trim((string) ($settings['graph_version'] ?? 'v23.0'), '/');

        return "{$baseUrl}/{$graphVersion}/{$phoneNumberId}/messages";
    }

    private function queueBridgeOutboundMessage(
        string $phone,
        string $message,
        ?string $groupId,
        ?string $groupName,
        array $requestPayload,
    ): WhatsAppSendResult {
        $hasDirectTarget = $phone !== '';
        $hasGroupTarget = filled($groupId);

        if (! $hasDirectTarget && ! $hasGroupTarget) {
            $record = $this->storeOutboundMessage(
                phone: $phone,
                message: $message,
                status: 'failed_configuration',
                rawPayload: [
                    'provider' => BridgeStatusService::PROVIDER,
                    'outbound_enabled' => true,
                    'request' => $requestPayload,
                    'target' => $this->buildTargetPayload($phone, $groupId, $groupName),
                    'error' => 'Missing bridge outbound target.',
                ],
                groupId: $groupId,
                groupName: $groupName,
            );

            return new WhatsAppSendResult(
                sent: false,
                status: 'failed_configuration',
                whatsappMessageId: null,
                httpStatusCode: null,
                messageRecord: $record,
                responseBody: null,
            );
        }

        $record = $this->storeOutboundMessage(
            phone: $phone,
            message: $message,
            status: 'queued_bridge',
            rawPayload: [
                'provider' => BridgeStatusService::PROVIDER,
                'outbound_enabled' => true,
                'request' => $requestPayload,
                'target' => $this->buildTargetPayload($phone, $groupId, $groupName),
                'queue' => 'bridge',
            ],
            groupId: $groupId,
            groupName: $groupName,
        );

        return new WhatsAppSendResult(
            sent: false,
            status: 'queued_bridge',
            whatsappMessageId: null,
            httpStatusCode: null,
            messageRecord: $record,
            responseBody: [
                'queued' => true,
                'provider' => BridgeStatusService::PROVIDER,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    private function storeOutboundMessage(
        string $phone,
        string $message,
        string $status,
        ?string $whatsappMessageId = null,
        ?array $rawPayload = null,
        ?string $businessPhoneNumberId = null,
        mixed $sentAt = null,
        ?string $groupId = null,
        ?string $groupName = null,
    ): WhatsappMessage {
        $contactId = null;

        if ($phone !== '') {
            $contactId = WhatsappContact::query()
                ->where('phone', $phone)
                ->orWhere('phone', '+' . ltrim($phone, '+'))
                ->value('id');
        }

        return WhatsappMessage::query()->create([
            'whatsapp_message_id' => $whatsappMessageId,
            'contact_id' => $contactId,
            'task_id' => null,
            'direction' => WhatsappMessageDirection::OUTBOUND,
            'from_phone' => null,
            'to_phone' => $phone,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'business_phone_number_id' => $businessPhoneNumberId ?: $this->apiSettingsService->getWhatsAppValue('phone_number_id'),
            'message_type' => 'text',
            'body' => $message,
            'media_url' => null,
            'status' => $status,
            'raw_payload' => $rawPayload,
            'received_at' => null,
            'sent_at' => $sentAt,
        ]);
    }

    private function isOutboundEnabled(): bool
    {
        return filter_var($this->apiSettingsService->getWhatsAppValue('outbound_enabled', false), FILTER_VALIDATE_BOOL) === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTargetPayload(string $phone, ?string $groupId, ?string $groupName): array
    {
        return array_filter([
            'type' => filled($groupId) ? 'group' : 'direct',
            'phone' => $phone !== '' ? $phone : null,
            'group_id' => $groupId,
            'group_name' => $groupName,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
