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

        return [
            new InboundMessageData(
                provider: $provider,
                providerMessageId: $this->nullableString($payload['provider_message_id'] ?? $payload['message_id'] ?? null),
                fromPhone: $this->normalizeWhatsAppPhone($payload['from'] ?? null),
                toPhone: $this->normalizeWhatsAppPhone($payload['to'] ?? $payload['group_id'] ?? null),
                senderName: $this->nullableString($payload['sender_name'] ?? null),
                messageType: $this->nullableString($payload['message_type'] ?? null) ?? 'text',
                body: $this->nullableString($payload['body'] ?? null),
                mediaUrl: $this->nullableString($payload['media_url'] ?? null),
                rawPayload: [
                    'provider' => $provider,
                    'bridge_payload' => is_array($payload['raw_payload'] ?? null) ? $payload['raw_payload'] : null,
                    'payload' => $payload,
                ],
                receivedAt: $this->parseTimestamp($payload['received_at'] ?? null) ?? CarbonImmutable::now(),
                groupId: $this->nullableString($payload['group_id'] ?? null),
                groupName: $this->nullableString($payload['group_name'] ?? null),
            ),
        ];
    }
}
