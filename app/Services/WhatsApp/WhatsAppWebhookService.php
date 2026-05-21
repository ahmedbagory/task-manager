<?php

namespace App\Services\WhatsApp;

use App\Services\Inbound\InboundMessageService;
use App\Services\Inbound\Parsers\MetaWhatsAppMessageParser;

class WhatsAppWebhookService
{
    public function __construct(
        private readonly MetaWhatsAppMessageParser $messageParser,
        private readonly InboundMessageService $inboundMessageService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleInbound(array $payload): void
    {
        $parsedMessages = $this->messageParser->parse($payload);

        foreach ($parsedMessages as $parsedMessage) {
            $this->inboundMessageService->handle($parsedMessage);
        }
    }
}
