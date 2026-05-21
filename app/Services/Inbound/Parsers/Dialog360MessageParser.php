<?php

namespace App\Services\Inbound\Parsers;

use App\Data\InboundMessageData;
use App\Services\Inbound\Parsers\Concerns\ParsesInboundPayload;
use Illuminate\Support\Arr;

class Dialog360MessageParser implements InboundMessageParser
{
    use ParsesInboundPayload;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, InboundMessageData>
     */
    public function parse(array $payload): array
    {
        $parsed = [];
        $hasStatusesOnlyEvent = false;

        foreach (Arr::wrap(Arr::get($payload, 'entry')) as $entry) {
            foreach (Arr::wrap(Arr::get($entry, 'changes')) as $change) {
                $value = Arr::get($change, 'value', []);

                if (! is_array($value)) {
                    continue;
                }

                $messages = Arr::wrap(Arr::get($value, 'messages'));
                $statuses = Arr::wrap(Arr::get($value, 'statuses'));
                $hasStatusesOnlyEvent = $hasStatusesOnlyEvent || (($messages === []) && ($statuses !== []));

                $metadata = Arr::get($value, 'metadata', []);
                $contactsByWaId = $this->indexContactsByWaId(Arr::wrap(Arr::get($value, 'contacts')));

                foreach ($messages as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $fromPhone = $this->normalizeWhatsAppPhone(Arr::get($message, 'from'));
                    $messageType = $this->nullableString(Arr::get($message, 'type')) ?? 'unknown';

                    $parsed[] = new InboundMessageData(
                        provider: '360dialog',
                        providerMessageId: $this->nullableString(Arr::get($message, 'id')),
                        fromPhone: $fromPhone,
                        toPhone: $this->nullableString(Arr::get($metadata, 'display_phone_number')),
                        senderName: $fromPhone ? ($contactsByWaId[$fromPhone] ?? null) : null,
                        messageType: $messageType,
                        body: $this->extractBody($message, $messageType),
                        mediaUrl: $this->extractMediaReference($message, $messageType),
                        rawPayload: [
                            'provider' => '360dialog',
                            'payload' => $payload,
                            'entry' => $entry,
                            'change' => $change,
                            'message' => $message,
                        ],
                        receivedAt: $this->parseTimestamp(Arr::get($message, 'timestamp')),
                        businessPhoneNumberId: $this->nullableString(Arr::get($metadata, 'phone_number_id')),
                    );
                }
            }
        }

        if ($parsed !== []) {
            return $parsed;
        }

        if ($hasStatusesOnlyEvent) {
            return [];
        }

        return [
            new InboundMessageData(
                provider: '360dialog',
                providerMessageId: null,
                fromPhone: null,
                toPhone: null,
                senderName: null,
                messageType: 'unknown_event',
                body: null,
                mediaUrl: null,
                rawPayload: [
                    'provider' => '360dialog',
                    'payload' => $payload,
                ],
                receivedAt: null,
            ),
        ];
    }

    /**
     * @param  array<int, mixed>  $contacts
     * @return array<string, ?string>
     */
    private function indexContactsByWaId(array $contacts): array
    {
        $indexed = [];

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $waId = $this->normalizeWhatsAppPhone(Arr::get($contact, 'wa_id'));

            if (blank($waId)) {
                continue;
            }

            $indexed[$waId] = $this->nullableString(Arr::get($contact, 'profile.name'));
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function extractBody(array $message, string $messageType): ?string
    {
        if ($messageType === 'text') {
            return $this->nullableString(Arr::get($message, 'text.body'));
        }

        if ($messageType === 'button') {
            return $this->nullableString(Arr::get($message, 'button.text'));
        }

        if ($messageType === 'interactive') {
            return $this->nullableString(Arr::get($message, 'interactive.button_reply.title'))
                ?? $this->nullableString(Arr::get($message, 'interactive.list_reply.title'));
        }

        return $this->nullableString(Arr::get($message, "{$messageType}.caption"));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function extractMediaReference(array $message, string $messageType): ?string
    {
        if (in_array($messageType, ['image', 'audio', 'video', 'document', 'sticker'], true)) {
            return $this->nullableString(Arr::get($message, "{$messageType}.id"))
                ?? $this->nullableString(Arr::get($message, "{$messageType}.link"));
        }

        return null;
    }
}
