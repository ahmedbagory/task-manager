<?php

namespace App\Enums;

enum TaskSource: string
{
    case MANUAL = 'manual';
    case WHATSAPP = 'whatsapp';
    case API = 'api';

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
            self::MANUAL => 'يدوي',
            self::WHATSAPP => 'واتساب',
            self::API => 'API',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MANUAL => 'gray',
            self::WHATSAPP => 'success',
            self::API => 'info',
        };
    }
}
