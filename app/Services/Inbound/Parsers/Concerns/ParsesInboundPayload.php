<?php

namespace App\Services\Inbound\Parsers\Concerns;

use Carbon\CarbonImmutable;

trait ParsesInboundPayload
{
    protected function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeWhatsAppPhone(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if (blank($value)) {
            return null;
        }

        if (str_starts_with(strtolower($value), 'whatsapp:')) {
            $value = trim(substr($value, strlen('whatsapp:')));
        }

        return $value === '' ? null : $value;
    }

    protected function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampUTC((int) $value);
        }

        if (is_string($value)) {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
