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

        if ($this->isGroupOrBroadcastJid($value)) {
            return null;
        }

        $hasLeadingPlus = str_starts_with($value, '+');
        $phone = preg_replace('/[@:].*$/', '', $value);
        $phone = preg_replace('/\D/', '', $phone);

        if (! $this->isValidPhoneNumber($phone)) {
            return null;
        }

        return $hasLeadingPlus ? '+'.$phone : $phone;
    }

    protected function isGroupOrBroadcastJid(?string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        $lower = strtolower($value);

        return str_ends_with($lower, '@g.us')
            || str_ends_with($lower, '@broadcast')
            || str_ends_with($lower, '@newsletter')
            || $lower === 'status@broadcast'
            || str_starts_with($lower, '120363');
    }

    protected function isValidPhoneNumber(?string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return false;
        }

        if (str_starts_with($digits, '120363')) {
            return false;
        }

        return true;
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
