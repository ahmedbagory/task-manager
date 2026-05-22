<?php

namespace App\Enums;

enum MobileNotificationRecipientStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case SKIPPED_NO_DEVICE = 'skipped_no_device';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::SENT => __('Sent'),
            self::SKIPPED_NO_DEVICE => __('Skipped (No Device)'),
            self::FAILED => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SENT => 'success',
            self::SKIPPED_NO_DEVICE => 'gray',
            self::FAILED => 'danger',
        };
    }
}
