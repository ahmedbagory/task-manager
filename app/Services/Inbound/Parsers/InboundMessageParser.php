<?php

namespace App\Services\Inbound\Parsers;

use App\Data\InboundMessageData;

interface InboundMessageParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, InboundMessageData>
     */
    public function parse(array $payload): array;
}
