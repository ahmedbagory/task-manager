<?php

namespace App\Enums;

enum TaskAssignmentStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

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
            self::ASSIGNED => __('Assigned'),
            self::ACCEPTED => __('Accepted'),
            self::COMPLETED => __('Completed'),
            self::REJECTED => __('Rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ASSIGNED => 'info',
            self::ACCEPTED => 'primary',
            self::COMPLETED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
