<?php

namespace App\Services\Inbound\Parsers;

use App\Data\InboundMessageData;
use App\Services\Inbound\Parsers\Concerns\ParsesInboundPayload;
use Carbon\CarbonImmutable;

class ManualWebhookMessageParser implements InboundMessageParser
{
    use ParsesInboundPayload;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, InboundMessageData>
     */
    public function parse(array $payload): array
    {
        $provider = $this->nullableString($payload['provider'] ?? $payload['source'] ?? null) ?? 'manual';
        $media = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $hasMedia = filter_var($media['has_media'] ?? false, FILTER_VALIDATE_BOOL) === true;
        $mediaType = $this->nullableString($media['type'] ?? null);
        $mediaPath = $this->nullableString($media['path'] ?? null);
        $mediaUrl = $this->nullableString($media['url'] ?? ($payload['media_url'] ?? null));
        $mediaRejected = filter_var($media['rejected'] ?? false, FILTER_VALIDATE_BOOL) === true;
        $mediaSize = isset($media['size']) && is_numeric($media['size']) ? (int) $media['size'] : null;

        return [
            new InboundMessageData(
                provider: $provider,
                providerMessageId: $this->nullableString($payload['provider_message_id'] ?? $payload['message_id'] ?? null),
                fromPhone: $this->normalizeWhatsAppPhone($payload['from'] ?? null),
                toPhone: $this->nullableString($payload['to'] ?? $payload['group_id'] ?? null),
                senderName: $this->nullableString($payload['sender_name'] ?? null),
                messageType: $this->nullableString($payload['message_type'] ?? null) ?? 'text',
                body: $this->nullableString($payload['body'] ?? null),
                mediaUrl: $hasMedia || $mediaRejected ? $mediaUrl : $this->nullableString($payload['media_url'] ?? null),
                rawPayload: [
                    'provider' => $provider,
                    'bridge_payload' => is_array($payload['raw_payload'] ?? null) ? $payload['raw_payload'] : null,
                    'payload' => $payload,
                ],
                receivedAt: $this->parseTimestamp($payload['received_at'] ?? null) ?? CarbonImmutable::now(),
                groupId: $this->nullableString($payload['group_id'] ?? null),
                groupName: $this->nullableString($payload['group_name'] ?? null),
                mediaType: $hasMedia || $mediaRejected ? $mediaType : null,
                mediaMime: $hasMedia || $mediaRejected ? $this->nullableString($media['mime_type'] ?? null) : null,
                mediaPath: $hasMedia || $mediaRejected ? $mediaPath : null,
                mediaName: $hasMedia || $mediaRejected ? $this->nullableString($media['original_name'] ?? $media['file_name'] ?? null) : null,
                mediaSize: $hasMedia || $mediaRejected ? $mediaSize : null,
                mediaRejected: $mediaRejected,
                mediaRejectReason: $mediaRejected ? $this->nullableString($media['reason'] ?? null) : null,
            ),
        ];
    }
}
