<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class InboundMessageData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public ?string $providerMessageId,
        public ?string $fromPhone,
        public ?string $toPhone,
        public ?string $senderName,
        public ?string $messageType,
        public ?string $body,
        public ?string $mediaUrl,
        public array $rawPayload,
        public ?CarbonImmutable $receivedAt = null,
        public ?string $businessPhoneNumberId = null,
        public ?string $groupId = null,
        public ?string $groupName = null,
        public ?string $mediaType = null,
        public ?string $mediaMime = null,
        public ?string $mediaPath = null,
        public ?string $mediaName = null,
        public ?int $mediaSize = null,
        public bool $mediaRejected = false,
        public ?string $mediaRejectReason = null,
    ) {}
}
