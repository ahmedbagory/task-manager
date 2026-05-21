<?php

namespace App\Enums;

enum WhatsappMessageDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::INBOUND => __('Inbound'),
            self::OUTBOUND => __('Outbound'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INBOUND => 'success',
            self::OUTBOUND => 'info',
        };
    }
}
