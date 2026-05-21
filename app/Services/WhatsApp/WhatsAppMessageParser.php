<?php

namespace App\Services\WhatsApp;

use App\Data\InboundMessageData;
use App\Services\Inbound\Parsers\MetaWhatsAppMessageParser;

class WhatsAppMessageParser
{
    public function __construct(
        private readonly MetaWhatsAppMessageParser $metaParser,
    ) {}

    /**
     * Backward-compatible adapter to legacy parsed array format.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function parseInboundMessages(array $payload): array
    {
        return collect($this->metaParser->parse($payload))
            ->map(function (InboundMessageData $data): array {
                return [
                    'whatsapp_message_id' => $data->providerMessageId,
                    'from_phone' => $data->fromPhone,
                    'to_phone' => $data->toPhone,
                    'business_phone_number_id' => $data->businessPhoneNumberId,
                    'message_type' => $data->messageType,
                    'body' => $data->body,
                    'media_url' => $data->mediaUrl,
                    'received_at' => $data->receivedAt,
                    'contact_name' => $data->senderName,
                    'raw_payload' => $data->rawPayload,
                ];
            })
            ->values()
            ->all();
    }
}
