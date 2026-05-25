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
    case AWAITING_REPORTER_CONFIRMATION = 'awaiting_reporter_confirmation';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case REOPENED = 'reopened';

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
            self::NEW => 'جديدة',
            self::PENDING_ASSIGNMENT => 'مفتوحة',
            self::ASSIGNED => 'مسندة',
            self::ACCEPTED => 'مقبولة',
            self::IN_PROGRESS => 'قيد التنفيذ',
            self::WAIT_RESPONSE => 'بانتظار رد',
            self::AWAITING_REPORTER_CONFIRMATION => 'بانتظار تأكيد صاحب الطلب',
            self::COMPLETED => 'مكتملة',
            self::CANCELLED => 'ملغاة',
            self::REJECTED => 'مرفوضة',
            self::REOPENED => 'معاد فتحها',
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
            self::AWAITING_REPORTER_CONFIRMATION => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::REJECTED => 'danger',
            self::REOPENED => 'warning',
        };
    }

    public static function formOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
