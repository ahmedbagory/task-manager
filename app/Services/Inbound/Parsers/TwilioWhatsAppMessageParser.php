<?php

namespace App\Services\Inbound\Parsers;

use App\Data\InboundMessageData;
use App\Services\Inbound\Parsers\Concerns\ParsesInboundPayload;
use Carbon\CarbonImmutable;

class TwilioWhatsAppMessageParser implements InboundMessageParser
{
    use ParsesInboundPayload;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, InboundMessageData>
     */
    public function parse(array $payload): array
    {
        $from = $this->normalizeWhatsAppPhone($payload['From'] ?? null);
        $to = $this->normalizeWhatsAppPhone($payload['To'] ?? null);

        if (blank($from) && blank($to)) {
            return [];
        }

        $numMedia = is_numeric($payload['NumMedia'] ?? null) ? (int) $payload['NumMedia'] : 0;
        $mediaUrl = $this->nullableString($payload['MediaUrl0'] ?? null);

        return [
            new InboundMessageData(
                provider: 'twilio',
                providerMessageId: $this->nullableString($payload['MessageSid'] ?? $payload['SmsSid'] ?? null),
                fromPhone: $from,
                toPhone: $to,
                senderName: $this->nullableString($payload['ProfileName'] ?? null),
                messageType: $numMedia > 0 ? 'media' : 'text',
                body: $this->nullableString($payload['Body'] ?? null),
                mediaUrl: $mediaUrl,
                rawPayload: [
                    'provider' => 'twilio',
                    'payload' => $payload,
                ],
                receivedAt: CarbonImmutable::now(),
            ),
        ];
    }
}
