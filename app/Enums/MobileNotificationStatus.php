<?php

namespace App\Enums;

enum MobileNotificationStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => __('Queued'),
            self::SENT => __('Sent'),
            self::FAILED => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::QUEUED => 'warning',
            self::SENT => 'success',
            self::FAILED => 'danger',
        };
    }
}
