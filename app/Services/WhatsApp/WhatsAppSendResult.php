<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;

final class WhatsAppSendResult
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        public readonly bool $sent,
        public readonly string $status,
        public readonly ?string $whatsappMessageId,
        public readonly ?int $httpStatusCode,
        public readonly WhatsappMessage $messageRecord,
        public readonly ?array $responseBody = null,
    ) {}
}
