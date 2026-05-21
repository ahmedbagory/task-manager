<?php

namespace App\Enums;

enum TaskStatus: string
{
    case NEW = 'new';
    case PENDING_ASSIGNMENT = 'pending_assignment';
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case IN_PROGRESS = 'in_progress';
    case WAIT_RESPONSE = 'wait_response';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
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
            self::NEW => __('New'),
            self::PENDING_ASSIGNMENT => __('Pending Assignment'),
            self::ASSIGNED => __('Assigned'),
            self::ACCEPTED => __('Accepted'),
            self::IN_PROGRESS => __('In Progress'),
            self::WAIT_RESPONSE => __('Waiting Response'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
            self::REJECTED => __('Rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEW => 'gray',
            self::PENDING_ASSIGNMENT => 'warning',
            self::ASSIGNED => 'info',
            self::ACCEPTED => 'primary',
            self::IN_PROGRESS => 'primary',
            self::WAIT_RESPONSE => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::REJECTED => 'danger',
        };
    }
}
